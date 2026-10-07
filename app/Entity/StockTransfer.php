<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Dokumen perpindahan stok antar-gudang (K-08). Perubahan stoknya sendiri
 * tercatat di stock_ledger: Issue di gudang asal + Receipt di gudang tujuan.
 */
final class StockTransfer
{
    /**
     * @param list<StockTransferItem> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $transferNo,
        public readonly int $fromWarehouseId,
        public readonly string $fromWarehouseName,
        public readonly int $toWarehouseId,
        public readonly string $toWarehouseName,
        public readonly ?string $note,
        public readonly int $createdBy,
        public readonly string $createdByName,
        public readonly string $createdAt,
        public readonly array $items,
    ) {
    }

    public function totalQty(): int
    {
        return array_sum(array_map(static fn (StockTransferItem $item): int => $item->qty, $this->items));
    }
}
