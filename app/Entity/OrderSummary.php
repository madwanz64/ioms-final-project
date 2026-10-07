<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Baris daftar order (PO maupun SO) untuk halaman list (VIEW-01, FIND-01).
 */
final class OrderSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $orderNo,
        public readonly string $partyName,
        public readonly string $warehouseName,
        public readonly string $status,
        public readonly string $orderDate,
        public readonly int $itemCount,
        public readonly int $total,
    ) {
    }
}
