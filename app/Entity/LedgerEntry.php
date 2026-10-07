<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu baris stock_ledger lengkap untuk laporan.
 */
final class LedgerEntry
{
    public function __construct(
        public readonly string $createdAt,
        public readonly string $sku,
        public readonly string $productName,
        public readonly string $warehouseName,
        public readonly string $movementType,
        public readonly int $quantity,
        public readonly string $refType,
        public readonly string $refId,
        public readonly string $performedBy,
    ) {
    }
}
