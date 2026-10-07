<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderSummary;
use App\Entity\Party;
use App\Entity\Product;
use App\Entity\Role;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;
use App\Entity\StockChange;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\OrderSearchCriteria;
use App\Repository\PaginatedResult;
use App\Repository\PartyRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;
use DateTimeImmutable;

/**
 * Sales Order, approval, dan goods issue (SO-01). Setiap aksi memeriksa
 * SalesOrderPolicy DI SERVER pada baris order yang sudah dikunci, sehingga
 * aturan tidak bisa dilewati dengan memanggil URL secara langsung.
 */
final class SalesOrderService
{
    private readonly OrderLineValidator $lineValidator;

    public function __construct(
        private readonly SalesOrderRepositoryInterface $orders,
        private readonly PartyRepositoryInterface $customers,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface $stockHistory,
        private readonly StockService $stock,
        private readonly TransactionManagerInterface $transactions,
        private readonly SalesOrderPolicy $policy,
        private readonly DateTimeImmutable $today,
    ) {
        $this->lineValidator = new OrderLineValidator($products);
    }

    /**
     * @return PaginatedResult<OrderSummary>
     */
    public function search(OrderSearchCriteria $criteria, User $viewer): PaginatedResult
    {
        // Cakupan data mengikuti SalesOrderPolicy::canView, diterapkan di query.
        $scoped = match ($viewer->role) {
            Role::Admin => $criteria->scoped(null, []),
            Role::Sales => $criteria->scoped($viewer->id, []),
            Role::WarehouseStaff => $criteria->scoped(null, [SalesOrderStatus::Draft->value]),
        };

        return $this->orders->search($scoped);
    }

    /**
     * Null bila tidak ada ATAU user tidak berhak melihat — controller menjawab 404
     * di kedua kasus agar keberadaan order milik orang lain tidak terungkap.
     */
    public function findVisible(int $id, User $viewer): ?SalesOrder
    {
        $order = $this->orders->findById($id);

        return $order !== null && $this->policy->canView($viewer, $order) ? $order : null;
    }

    /**
     * @return list<StockMovement>
     */
    public function issues(SalesOrder $order): array
    {
        return $this->stockHistory->movementsByReference('SO', $order->orderNo);
    }

    /**
     * @return array{customers: list<Party>, warehouses: list<Warehouse>, products: list<Product>}
     */
    public function formOptions(): array
    {
        return [
            'customers' => array_values(array_filter($this->customers->all(), static fn (Party $c): bool => $c->active)),
            'warehouses' => array_values(array_filter($this->warehouses->all(), static fn (Warehouse $w): bool => $w->active)),
            'products' => $this->products->allActive(),
        ];
    }

    /**
     * Buat SO berstatus Draft milik $actor. Harga diambil dari harga jual katalog
     * (tidak bisa diisi pengguna). Qty dicek terhadap stok gudang asal saat ini;
     * pengecekan final yang mengikat terjadi saat goods issue (dengan lock).
     *
     * @param array<string, string> $input customer_id, warehouse_id, order_date
     * @param list<array<string, string>> $lines sku, qty
     * @throws ValidationException|AuthorizationException
     */
    public function create(array $input, array $lines, User $actor): int
    {
        if (!$this->policy->canCreate($actor)) {
            throw new AuthorizationException('Anda tidak berwenang membuat Sales Order.');
        }

        $validator = new InputValidator($input);
        $customer = $this->customers->findById((int) $validator->positiveId('customer_id'));
        if ($customer === null || !$customer->active) {
            $validator->addError('customer_id', 'Pilih customer yang aktif.');
        }
        $warehouse = $this->warehouses->findById((int) $validator->positiveId('warehouse_id'));
        if ($warehouse === null || !$warehouse->active) {
            $validator->addError('warehouse_id', 'Pilih gudang asal yang aktif.');
        }
        $orderDate = $validator->dateNotAfter('order_date', 'Tanggal order', $this->today);
        $orderLines = $this->lineValidator->validate($validator, $lines, null);

        if ($warehouse !== null && $warehouse->active) {
            foreach ($orderLines as $index => $line) {
                $available = $this->availableStock($line->sku, $warehouse->id);
                if ($line->qty > 0 && $line->qty > $available && !$validator->hasError('items.' . $index . '.qty')) {
                    $validator->addError('items.' . $index . '.qty', sprintf('Stok tersedia di %s hanya %d.', $warehouse->name, $available));
                }
            }
        }
        $validator->throwIfInvalid();

        return $this->orders->create((int) $customer?->id, (int) $warehouse?->id, $actor->id, $orderDate, $orderLines);
    }

    /**
     * Draft -> PendingApproval (diajukan pembuatnya, atau Admin).
     */
    public function submit(int $id, User $actor): SalesOrder
    {
        return $this->transition($id, $actor, fn (SalesOrder $o): bool => $this->policy->canSubmit($actor, $o),
            SalesOrderStatus::Draft, SalesOrderStatus::PendingApproval, null);
    }

    /**
     * PendingApproval -> Approved. Hanya Admin yang BUKAN pembuat order (segregation of duties).
     */
    public function approve(int $id, User $actor): SalesOrder
    {
        return $this->transition($id, $actor, fn (SalesOrder $o): bool => $this->policy->canReview($actor, $o),
            SalesOrderStatus::PendingApproval, SalesOrderStatus::Approved, $actor->id);
    }

    /**
     * PendingApproval -> Cancelled (ditolak). Aturan siapa yang boleh sama dengan approve.
     */
    public function reject(int $id, User $actor): SalesOrder
    {
        return $this->transition($id, $actor, fn (SalesOrder $o): bool => $this->policy->canReview($actor, $o),
            SalesOrderStatus::PendingApproval, SalesOrderStatus::Cancelled, null);
    }

    public function cancel(int $id, User $actor): SalesOrder
    {
        return $this->transition($id, $actor, fn (SalesOrder $o): bool => $this->policy->canCancel($actor, $o), null, SalesOrderStatus::Cancelled, null);
    }

    /**
     * Goods issue (SO-01, ARCH-02): dalam SATU transaksi — kunci SO, pastikan
     * berstatus Approved, kurangi stok gudang asal + tulis ledger Issue lewat
     * StockService (lock berurutan, tolak bila stok kurang), lalu Fulfilled.
     * Bila stok tidak cukup, seluruh transaksi dibatalkan dan SO tetap Approved.
     *
     * @throws BusinessRuleException|AuthorizationException
     */
    public function fulfill(int $id, User $actor): SalesOrder
    {
        return $this->transactions->run(function () use ($id, $actor): SalesOrder {
            $order = $this->lockVisible($id, $actor);
            if ($order->status !== SalesOrderStatus::Approved) {
                throw new BusinessRuleException('Goods issue hanya dapat diproses untuk Sales Order berstatus Approved.');
            }
            if (!$this->policy->canFulfill($actor, $order)) {
                throw new AuthorizationException('Anda tidak berwenang memproses goods issue.');
            }

            $changes = array_map(
                static fn ($item): StockChange => new StockChange($item->sku, $order->warehouseId, -$item->qty, StockChange::TYPE_ISSUE, 'SO', $order->orderNo, $actor->id),
                $order->items,
            );
            $this->stock->apply($changes);
            $this->orders->updateStatus($order->id, SalesOrderStatus::Fulfilled);

            return $this->orders->findById($order->id) ?? $order;
        });
    }

    /**
     * @param callable(SalesOrder): bool $allowed
     * @param SalesOrderStatus|null $requiredStatus null = status apa pun yang diizinkan $allowed
     */
    private function transition(int $id, User $actor, callable $allowed, ?SalesOrderStatus $requiredStatus, SalesOrderStatus $target, ?int $approvedBy): SalesOrder
    {
        return $this->transactions->run(function () use ($id, $actor, $allowed, $requiredStatus, $target, $approvedBy): SalesOrder {
            // Dikunci: dua Admin yang menekan approve/reject bersamaan tidak bisa sama-sama berhasil.
            $order = $this->lockVisible($id, $actor);
            if ($requiredStatus !== null && $order->status !== $requiredStatus) {
                throw new BusinessRuleException(sprintf('Aksi ini hanya untuk order berstatus %s (status saat ini: %s).', $requiredStatus->label(), $order->status->label()));
            }
            if ($order->status->isFinal()) {
                throw new BusinessRuleException('Order yang sudah Fulfilled atau Cancelled tidak dapat diubah.');
            }
            if (!$allowed($order)) {
                throw new AuthorizationException($this->denialReason($actor, $order, $target));
            }
            $this->orders->updateStatus($order->id, $target, $approvedBy);

            return $this->orders->findById($order->id) ?? $order;
        });
    }

    private function lockVisible(int $id, User $actor): SalesOrder
    {
        $order = $this->orders->lockById($id);
        if ($order === null || !$this->policy->canView($actor, $order)) {
            throw new NotFoundException('Sales Order tidak ditemukan.');
        }

        return $order;
    }

    private function denialReason(User $actor, SalesOrder $order, SalesOrderStatus $target): string
    {
        if ($target === SalesOrderStatus::Approved && $order->isCreatedBy($actor)) {
            return 'Anda tidak dapat menyetujui Sales Order yang Anda buat sendiri.';
        }

        return 'Anda tidak berwenang melakukan aksi ini pada Sales Order tersebut.';
    }

    private function availableStock(string $sku, int $warehouseId): int
    {
        foreach ($this->products->stockLevels($sku) as $level) {
            if ($level->warehouseId === $warehouseId) {
                return $level->quantity;
            }
        }

        return 0;
    }
}
