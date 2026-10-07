<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu produk aktif yang total stoknya di bawah reorder point (JOB-01).
 */
final class LowStockLine
{
    /**
     * @param list<StockLevel> $levels
     */
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly string $categoryName,
        public readonly int $totalStock,
        public readonly int $reorderPoint,
        public readonly array $levels,
    ) {
    }

    /**
     * Kekurangan minimal agar kembali ke reorder point.
     */
    public function shortage(): int
    {
        return max(0, $this->reorderPoint - $this->totalStock);
    }
}
