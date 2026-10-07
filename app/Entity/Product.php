<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Produk master (PRD-01). Harga disimpan sebagai rupiah utuh (int) — seluruh
 * seed dan UI tidak memakai sen, jadi int menghindari pembulatan float.
 *
 * $updatedAt adalah versi baris untuk optimistic lock form edit; null pada
 * produk yang belum tersimpan.
 */
final class Product
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly int $categoryId,
        public readonly string $unit,
        public readonly int $buyPrice,
        public readonly int $sellPrice,
        public readonly int $reorderPoint,
        public readonly ?string $imageUrl,
        public readonly bool $active,
        public readonly ?string $updatedAt = null,
    ) {
    }
}
