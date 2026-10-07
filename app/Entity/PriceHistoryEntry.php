<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu baris product_price_history (read model untuk halaman detail produk).
 */
final class PriceHistoryEntry
{
    public function __construct(
        public readonly string $changedAt,
        public readonly string $changedByName,
        public readonly ?int $oldBuyPrice,
        public readonly int $newBuyPrice,
        public readonly ?int $oldSellPrice,
        public readonly int $newSellPrice,
    ) {
    }

    public function isInitial(): bool
    {
        return $this->oldBuyPrice === null && $this->oldSellPrice === null;
    }

    public function sellPriceChanged(): bool
    {
        return $this->oldSellPrice !== $this->newSellPrice;
    }

    public function buyPriceChanged(): bool
    {
        return $this->oldBuyPrice !== $this->newBuyPrice;
    }
}
