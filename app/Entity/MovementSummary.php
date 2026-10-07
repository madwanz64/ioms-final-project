<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Rekap pergerakan stok satu produk di satu gudang dalam rentang tanggal.
 */
final class MovementSummary
{
    public function __construct(
        public readonly string $sku,
        public readonly string $productName,
        public readonly string $warehouseName,
        public readonly int $receipt,
        public readonly int $issue,
        public readonly int $adjustment,
    ) {
    }

    public function net(): int
    {
        return $this->receipt + $this->issue + $this->adjustment;
    }
}
