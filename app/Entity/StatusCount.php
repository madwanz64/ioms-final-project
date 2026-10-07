<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Hasil agregasi jumlah & nilai order per status (dashboard & laporan status order).
 */
final class StatusCount
{
    public function __construct(
        public readonly string $orderType,
        public readonly string $status,
        public readonly int $count,
        public readonly int $total,
    ) {
    }
}
