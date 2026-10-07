<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Baris daftar transfer stok.
 */
final class TransferSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $transferNo,
        public readonly string $fromWarehouseName,
        public readonly string $toWarehouseName,
        public readonly string $createdByName,
        public readonly string $createdAt,
        public readonly int $itemCount,
        public readonly int $totalQty,
    ) {
    }
}
