<?php

declare(strict_types=1);

namespace App\Entity;

final class SalesOrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $productName,
        public readonly string $unit,
        public readonly int $qty,
        public readonly int $price,
    ) {
    }

    public function subtotal(): int
    {
        return $this->qty * $this->price;
    }
}
