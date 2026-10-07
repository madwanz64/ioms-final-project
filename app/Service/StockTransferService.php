<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\StockChange;
use App\Entity\StockMovement;
use App\Entity\StockTransfer;
use App\Entity\TransferSummary;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\OrderSearchCriteria;
use App\Repository\PaginatedResult;
use App\Repository\ProductRepositoryInterface;
use App\Repository\StockRepositoryInterface;
use App\Repository\StockTransferRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;

/**
 * Perpindahan stok antar-gudang (K-08). Satu transaksi: simpan dokumen transfer,
 * lalu untuk setiap item catat Issue di gudang asal dan Receipt di gudang tujuan
 * lewat StockService (lock berurutan + tolak bila stok kurang, ADR-001). Bila satu
 * item saja kurang, seluruh transfer dibatalkan. Total stok semua gudang tidak berubah.
 */
final class StockTransferService
{
    private const MAX_NOTE = 255;

    private readonly OrderLineValidator $lineValidator;

    public function __construct(
        private readonly StockTransferRepositoryInterface $transfers,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface $stockHistory,
        private readonly StockService $stock,
        private readonly TransactionManagerInterface $transactions,
    ) {
        $this->lineValidator = new OrderLineValidator($products);
    }

    /**
     * @return PaginatedResult<TransferSummary>
     */
    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        return $this->transfers->search($criteria);
    }

    public function find(int $id): ?StockTransfer
    {
        return $this->transfers->findById($id);
    }

    /**
     * @return list<StockMovement>
     */
    public function movements(StockTransfer $transfer): array
    {
        return $this->stockHistory->movementsByReference('TRF', $transfer->transferNo);
    }

    /**
     * @return array{warehouses: list<Warehouse>, products: list<Product>}
     */
    public function formOptions(): array
    {
        return [
            'warehouses' => array_values(array_filter($this->warehouses->all(), static fn (Warehouse $w): bool => $w->active)),
            'products' => $this->products->allActive(),
        ];
    }

    /**
     * @param array<string, string> $input from_warehouse_id, to_warehouse_id, note
     * @param list<array<string, string>> $lines sku, qty
     * @throws ValidationException|BusinessRuleException
     */
    public function create(array $input, array $lines, User $actor): int
    {
        $validator = new InputValidator($input);
        $from = $this->activeWarehouse($validator, 'from_warehouse_id', 'Pilih gudang asal yang aktif.');
        $to = $this->activeWarehouse($validator, 'to_warehouse_id', 'Pilih gudang tujuan yang aktif.');
        if ($from !== null && $to !== null && $from->id === $to->id) {
            $validator->addError('to_warehouse_id', 'Gudang tujuan harus berbeda dari gudang asal.');
        }
        $note = $validator->optionalText('note', 'Catatan', self::MAX_NOTE);
        $transferLines = $this->lineValidator->validate($validator, $lines, null);
        $validator->throwIfInvalid();
        if ($from === null || $to === null) {
            throw new \LogicException('Gudang kosong lolos validasi.');
        }

        return $this->transactions->run(function () use ($from, $to, $note, $transferLines, $actor): int {
            $id = $this->transfers->create($from->id, $to->id, $actor->id, $note, $transferLines);
            $transfer = $this->transfers->findById($id) ?? throw new \LogicException('Transfer baru tidak terbaca.');

            $changes = [];
            foreach ($transferLines as $line) {
                $changes[] = new StockChange($line->sku, $from->id, -$line->qty, StockChange::TYPE_ISSUE, 'TRF', $transfer->transferNo, $actor->id);
                $changes[] = new StockChange($line->sku, $to->id, $line->qty, StockChange::TYPE_RECEIPT, 'TRF', $transfer->transferNo, $actor->id);
            }
            // Stok kurang -> BusinessRuleException -> transaksi (termasuk dokumen) dibatalkan.
            $this->stock->apply($changes);

            return $id;
        });
    }

    private function activeWarehouse(InputValidator $validator, string $field, string $message): ?Warehouse
    {
        $id = $validator->positiveId($field);
        $warehouse = $id === null ? null : $this->warehouses->findById($id);
        if ($warehouse === null || !$warehouse->active) {
            $validator->addError($field, $message);

            return null;
        }

        return $warehouse;
    }
}
