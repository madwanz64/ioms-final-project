<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu perubahan stok yang akan dicatat: satu baris stock_ledger sekaligus
 * perubahan product_stock sebesar $delta (positif = masuk, negatif = keluar).
 */
final class StockChange
{
    public const TYPE_RECEIPT = 'Receipt';
    public const TYPE_ISSUE = 'Issue';

    public function __construct(
        public readonly string $sku,
        public readonly int $warehouseId,
        public readonly int $delta,
        public readonly string $movementType,
        public readonly string $refType,
        public readonly string $refId,
        public readonly int $performedBy,
    ) {
    }

    /**
     * Kunci gabungan produk+gudang untuk peta quantity hasil lockQuantities().
     */
    public static function key(string $sku, int $warehouseId): string
    {
        return $sku . '@' . $warehouseId;
    }
}
