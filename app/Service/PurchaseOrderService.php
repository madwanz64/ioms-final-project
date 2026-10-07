<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderSummary;
use App\Entity\Party;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderStatus;
use App\Entity\StockChange;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\OrderSearchCriteria;
use App\Repository\PaginatedResult;
use App\Repository\PartyRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\StockRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;
use DateTimeImmutable;

/**
 * Purchase Order & goods receipt (PO-01).
 */
final class PurchaseOrderService
{
    private readonly OrderLineValidator $lineValidator;

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $orders,
        private readonly PartyRepositoryInterface $suppliers,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface $stockHistory,
        private readonly StockService $stock,
        private readonly TransactionManagerInterface $transactions,
        private readonly DateTimeImmutable $today,
    ) {
        // Kolaborator murni (tanpa I/O sendiri) — cukup dirakit di sini dari repository yang sama.
        $this->lineValidator = new OrderLineValidator($products);
    }

    /**
     * @return PaginatedResult<OrderSummary>
     */
    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        return $this->orders->search($criteria);
    }

    public function find(int $id): ?PurchaseOrder
    {
        return $this->orders->findById($id);
    }

    /**
     * @return list<StockMovement>
     */
    public function receipts(PurchaseOrder $order): array
    {
        return $this->stockHistory->movementsByReference('PO', $order->orderNo);
    }

    /**
     * Pilihan pada form PO: hanya master data yang aktif.
     *
     * @return array{suppliers: list<Party>, warehouses: list<Warehouse>, products: list<Product>}
     */
    public function formOptions(): array
    {
        return [
            'suppliers' => array_values(array_filter($this->suppliers->all(), static fn (Party $s): bool => $s->active)),
            'warehouses' => array_values(array_filter($this->warehouses->all(), static fn (Warehouse $w): bool => $w->active)),
            'products' => $this->products->allActive(),
        ];
    }

    /**
     * Buat PO berstatus Draft. Admin maupun Warehouse Staff (mengusulkan) boleh.
     *
     * @param array<string, string> $input supplier_id, warehouse_id, order_date
     * @param list<array<string, string>> $lines baris item: sku, qty, buy_price
     * @throws ValidationException
     */
    public function create(array $input, array $lines): int
    {
        $validator = new InputValidator($input);

        $supplier = $this->activeOrNull($this->suppliers->findById((int) $validator->positiveId('supplier_id')));
        if ($supplier === null) {
            $validator->addError('supplier_id', 'Pilih supplier yang aktif.');
        }
        $warehouse = $this->activeOrNull($this->warehouses->findById((int) $validator->positiveId('warehouse_id')));
        if ($warehouse === null) {
            $validator->addError('warehouse_id', 'Pilih gudang tujuan yang aktif.');
        }
        $orderDate = $validator->dateNotAfter('order_date', 'Tanggal order', $this->today);
        $orderLines = $this->lineValidator->validate($validator, $lines, 'buy_price', 'Harga beli');
        $validator->throwIfInvalid();

        return $this->orders->create((int) $supplier?->id, (int) $warehouse?->id, $orderDate, $orderLines);
    }

    /**
     * Draft -> Ordered (PO dikirim ke supplier). Hanya Admin (diatur di router).
     *
     * @throws BusinessRuleException
     */
    public function markOrdered(int $id): PurchaseOrder
    {
        return $this->transition($id, static fn (PurchaseOrderStatus $s): bool => $s->canBeOrdered(), PurchaseOrderStatus::Ordered,
            'Hanya PO berstatus Draft yang dapat dipesan.');
    }

    /**
     * @throws BusinessRuleException
     */
    public function cancel(int $id): PurchaseOrder
    {
        return $this->transition($id, static fn (PurchaseOrderStatus $s): bool => $s->canBeCancelled(), PurchaseOrderStatus::Cancelled,
            'PO yang sudah menerima barang (atau sudah selesai/dibatalkan) tidak dapat dibatalkan.');
    }

    /**
     * Goods receipt (PO-01): dalam SATU transaksi — kunci PO, validasi qty
     * terhadap sisa yang belum diterima, tambah stok + tulis ledger Receipt,
     * tambah received_qty, lalu ubah status menjadi PartiallyReceived/Received.
     *
     * @param array<array-key, string> $quantities itemId => qty diterima sekarang ("" / "0" = tidak ada)
     * @throws ValidationException|BusinessRuleException
     */
    public function receive(int $id, array $quantities, User $actor): PurchaseOrder
    {
        return $this->transactions->run(function () use ($id, $quantities, $actor): PurchaseOrder {
            $order = $this->orders->lockById($id) ?? throw new BusinessRuleException('Purchase Order tidak ditemukan.');
            if (!$order->status->canReceive()) {
                throw new BusinessRuleException('Barang hanya dapat diterima untuk PO berstatus Ordered atau Partially Received.');
            }

            $received = $this->validateReceipt($order, $quantities);

            $changes = [];
            foreach ($received as $itemId => $qty) {
                $item = $order->item($itemId);
                if ($item !== null) {
                    $changes[] = new StockChange($item->sku, $order->warehouseId, $qty, StockChange::TYPE_RECEIPT, 'PO', $order->orderNo, $actor->id);
                }
            }
            $this->stock->apply($changes);
            foreach ($received as $itemId => $qty) {
                $this->orders->addReceivedQty($itemId, $qty);
            }
            $this->orders->updateStatus($order->id, $order->statusAfterReceipt($received));

            return $this->orders->findById($order->id) ?? $order;
        });
    }

    /**
     * @param array<array-key, string> $quantities
     * @return array<int, int> itemId => qty (hanya yang > 0)
     * @throws ValidationException
     */
    private function validateReceipt(PurchaseOrder $order, array $quantities): array
    {
        $errors = [];
        $received = [];
        foreach ($order->items as $item) {
            $raw = trim($quantities[$item->id] ?? '');
            if ($raw === '' || $raw === '0') {
                continue;
            }
            $field = 'receive.' . $item->id;
            if (!ctype_digit($raw)) {
                $errors[$field] = 'Qty diterima harus bilangan bulat >= 0.';
            } elseif ((int) $raw > $item->remainingQty()) {
                $errors[$field] = sprintf('Maksimal %d (sisa yang belum diterima).', $item->remainingQty());
            } else {
                $received[$item->id] = (int) $raw;
            }
        }
        if ($errors === [] && $received === []) {
            $errors['receive'] = 'Isi qty diterima minimal untuk satu item.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $received;
    }

    /**
     * @param callable(PurchaseOrderStatus): bool $allowed
     */
    private function transition(int $id, callable $allowed, PurchaseOrderStatus $target, string $error): PurchaseOrder
    {
        return $this->transactions->run(function () use ($id, $allowed, $target, $error): PurchaseOrder {
            // Dikunci agar tidak berbalapan dengan goods receipt pada PO yang sama.
            $order = $this->orders->lockById($id) ?? throw new BusinessRuleException('Purchase Order tidak ditemukan.');
            if (!$allowed($order->status)) {
                throw new BusinessRuleException($error);
            }
            $this->orders->updateStatus($id, $target);

            return $this->orders->findById($id) ?? $order;
        });
    }

    /**
     * @template T of Party|Warehouse
     * @param T|null $entity
     * @return T|null
     */
    private function activeOrNull(Party|Warehouse|null $entity): Party|Warehouse|null
    {
        return $entity !== null && $entity->active ? $entity : null;
    }
}
