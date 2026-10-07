<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu perubahan harga standar produk yang akan dicatat ke
 * product_price_history. Harga lama null = harga awal saat produk dibuat.
 */
final class PriceChange
{
    public function __construct(
        public readonly string $sku,
        public readonly ?int $oldBuyPrice,
        public readonly int $newBuyPrice,
        public readonly ?int $oldSellPrice,
        public readonly int $newSellPrice,
        public readonly int $changedBy,
    ) {
    }

    public static function initial(Product $product, int $changedBy): self
    {
        return new self($product->sku, null, $product->buyPrice, null, $product->sellPrice, $changedBy);
    }

    /**
     * Null bila harga beli dan harga jual sama-sama tidak berubah: edit nama,
     * stok minimum, dll. tidak menambah baris riwayat harga.
     */
    public static function between(Product $before, Product $after, int $changedBy): ?self
    {
        if ($before->buyPrice === $after->buyPrice && $before->sellPrice === $after->sellPrice) {
            return null;
        }

        return new self($after->sku, $before->buyPrice, $after->buyPrice, $before->sellPrice, $after->sellPrice, $changedBy);
    }
}
