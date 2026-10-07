<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Repository\OrderSearchCriteria;
use App\Repository\PaginatedResult;
use App\Repository\PurchaseOrderRepositoryInterface;
use RuntimeException;

final class InMemoryPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /** @var array<int, PurchaseOrder> */
    private array $orders = [];

    /** @var list<array{supplierId: int, warehouseId: int, orderDate: string, lines: list<\App\Entity\NewOrderLine>}> */
    public array $created = [];

    public function add(PurchaseOrder $order): void
    {
        $this->orders[$order->id] = $order;
    }

    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        return new PaginatedResult([], 0, 1, $criteria->perPage);
    }

    public function findById(int $id): ?PurchaseOrder
    {
        return $this->orders[$id] ?? null;
    }

    public function lockById(int $id): ?PurchaseOrder
    {
        return $this->findById($id);
    }

    public function create(int $supplierId, int $warehouseId, string $orderDate, array $lines): int
    {
        $this->created[] = ['supplierId' => $supplierId, 'warehouseId' => $warehouseId, 'orderDate' => $orderDate, 'lines' => $lines];

        return 100 + count($this->created);
    }

    public function updateStatus(int $id, PurchaseOrderStatus $status): void
    {
        $order = $this->orders[$id];
        $this->orders[$id] = new PurchaseOrder($order->id, $order->orderNo, $order->supplierId, $order->supplierName,
            $order->warehouseId, $order->warehouseName, $status, $order->orderDate, $order->items);
    }

    public function addReceivedQty(int $itemId, int $qty): void
    {
        foreach ($this->orders as $id => $order) {
            $items = [];
            foreach ($order->items as $item) {
                if ($item->id === $itemId) {
                    if ($item->receivedQty + $qty > $item->qty) {
                        throw new RuntimeException('Melebihi qty pesanan.');
                    }
                    $item = new PurchaseOrderItem($item->id, $item->sku, $item->productName, $item->unit, $item->qty, $item->receivedQty + $qty, $item->buyPrice);
                }
                $items[] = $item;
            }
            $this->orders[$id] = new PurchaseOrder($order->id, $order->orderNo, $order->supplierId, $order->supplierName,
                $order->warehouseId, $order->warehouseName, $order->status, $order->orderDate, $items);
        }
    }
}
