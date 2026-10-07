<?php

declare(strict_types=1);

namespace App\Entity;

final class PurchaseOrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $productName,
        public readonly string $unit,
        public readonly int $qty,
        public readonly int $receivedQty,
        public readonly int $buyPrice,
    ) {
    }

    /**
     * Sisa qty yang belum diterima (PO-01: tetap tercatat saat penerimaan sebagian).
     */
    public function remainingQty(): int
    {
        return $this->qty - $this->receivedQty;
    }

    public function subtotal(): int
    {
        return $this->qty * $this->buyPrice;
    }
}
