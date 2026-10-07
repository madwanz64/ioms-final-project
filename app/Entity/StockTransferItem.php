<?php

declare(strict_types=1);

namespace App\Entity;

final class StockTransferItem
{
    public function __construct(
        public readonly string $sku,
        public readonly string $productName,
        public readonly string $unit,
        public readonly int $qty,
    ) {
    }
}
