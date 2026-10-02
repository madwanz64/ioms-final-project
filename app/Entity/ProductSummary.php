<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Baris daftar produk: data produk + nama kategori + total stok semua gudang.
 */
final class ProductSummary
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly string $categoryName,
        public readonly int $sellPrice,
        public readonly int $reorderPoint,
        public readonly int $totalStock,
        public readonly bool $active,
    ) {
    }

    /**
     * Low stock = total stok di bawah reorder point (sama dengan filter SQL
     * `HAVING total_stock < reorder_point` di MySqlProductRepository).
     */
    public function isLowStock(): bool
    {
        return $this->totalStock < $this->reorderPoint;
    }
}
