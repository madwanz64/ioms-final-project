<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu baris stock_ledger (read model untuk halaman detail produk).
 */
final class StockMovement
{
    public function __construct(
        public readonly string $createdAt,
        public readonly string $warehouseName,
        public readonly string $type,
        public readonly int $quantity,
        public readonly string $refId,
        public readonly string $sku = '',
    ) {
    }
}
