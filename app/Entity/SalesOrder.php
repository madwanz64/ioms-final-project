<?php

declare(strict_types=1);

namespace App\Entity;

final class SalesOrder
{
    /**
     * @param list<SalesOrderItem> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $orderNo,
        public readonly int $customerId,
        public readonly string $customerName,
        public readonly int $warehouseId,
        public readonly string $warehouseName,
        public readonly int $createdBy,
        public readonly string $createdByName,
        public readonly ?int $approvedBy,
        public readonly ?string $approvedByName,
        public readonly SalesOrderStatus $status,
        public readonly string $orderDate,
        public readonly array $items,
    ) {
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (SalesOrderItem $item): int => $item->subtotal(), $this->items));
    }

    public function isCreatedBy(User $user): bool
    {
        return $this->createdBy === $user->id;
    }
}
