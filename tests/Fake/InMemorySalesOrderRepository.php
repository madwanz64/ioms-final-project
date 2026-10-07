<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\NewOrderLine;
use App\Entity\OrderSummary;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use App\Repository\OrderSearchCriteria;
use App\Repository\PaginatedResult;
use App\Repository\SalesOrderRepositoryInterface;

final class InMemorySalesOrderRepository implements SalesOrderRepositoryInterface
{
    /** @var array<int, SalesOrder> */
    private array $orders = [];

    public function add(SalesOrder $order): void
    {
        $this->orders[$order->id] = $order;
    }

    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        $rows = [];
        foreach ($this->orders as $order) {
            if (($criteria->createdBy !== null && $order->createdBy !== $criteria->createdBy)
                || in_array($order->status->value, $criteria->hiddenStatuses, true)) {
                continue;
            }
            $rows[] = new OrderSummary($order->id, $order->orderNo, $order->customerName, $order->warehouseName,
                $order->status->value, $order->orderDate, count($order->items), $order->total());
        }

        return new PaginatedResult($rows, count($rows), 1, $criteria->perPage);
    }

    public function findById(int $id): ?SalesOrder
    {
        return $this->orders[$id] ?? null;
    }

    public function lockById(int $id): ?SalesOrder
    {
        return $this->findById($id);
    }

    /**
     * @param list<NewOrderLine> $lines
     */
    public function create(int $customerId, int $warehouseId, int $createdBy, string $orderDate, array $lines): int
    {
        $id = $this->orders === [] ? 1 : max(array_keys($this->orders)) + 1;
        $items = [];
        foreach ($lines as $i => $line) {
            $items[] = new SalesOrderItem($id * 100 + $i, $line->sku, $line->sku, 'pcs', $line->qty, $line->price);
        }
        $this->orders[$id] = new SalesOrder($id, sprintf('SO-TEST-%04d', $id), $customerId, 'Customer', $warehouseId, 'Gudang',
            $createdBy, 'Pembuat', null, null, SalesOrderStatus::Draft, $orderDate, $items);

        return $id;
    }

    public function updateStatus(int $id, SalesOrderStatus $status, ?int $approvedBy = null): void
    {
        $o = $this->orders[$id];
        $this->orders[$id] = new SalesOrder($o->id, $o->orderNo, $o->customerId, $o->customerName, $o->warehouseId, $o->warehouseName,
            $o->createdBy, $o->createdByName, $approvedBy ?? $o->approvedBy, $o->approvedByName, $status, $o->orderDate, $o->items);
    }
}
