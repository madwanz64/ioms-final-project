<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Stok satu produk di satu gudang (WH-01).
 */
final class StockLevel
{
    public function __construct(
        public readonly int $warehouseId,
        public readonly string $warehouseName,
        public readonly int $quantity,
    ) {
    }
}
