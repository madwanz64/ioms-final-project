<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Satu baris item order yang sudah tervalidasi, sebelum disimpan.
 */
final class NewOrderLine
{
    public function __construct(
        public readonly string $sku,
        public readonly int $qty,
        public readonly int $price,
    ) {
    }
}
