<?php

declare(strict_types=1);

namespace App\Entity;

final class PurchaseOrder
{
    /**
     * @param list<PurchaseOrderItem> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $orderNo,
        public readonly int $supplierId,
        public readonly string $supplierName,
        public readonly int $warehouseId,
        public readonly string $warehouseName,
        public readonly PurchaseOrderStatus $status,
        public readonly string $orderDate,
        public readonly array $items,
    ) {
    }

    public function item(int $itemId): ?PurchaseOrderItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        return null;
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (PurchaseOrderItem $item): int => $item->subtotal(), $this->items));
    }

    /**
     * Status setelah menerima barang sebanyak $receivedNow (itemId => qty):
     * Received bila semua item lengkap, selain itu PartiallyReceived.
     *
     * @param array<int, int> $receivedNow
     */
    public function statusAfterReceipt(array $receivedNow): PurchaseOrderStatus
    {
        foreach ($this->items as $item) {
            if ($item->receivedQty + ($receivedNow[$item->id] ?? 0) < $item->qty) {
                return PurchaseOrderStatus::PartiallyReceived;
            }
        }

        return PurchaseOrderStatus::Received;
    }
}
