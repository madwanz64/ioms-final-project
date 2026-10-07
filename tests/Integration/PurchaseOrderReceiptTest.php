<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\NewOrderLine;
use App\Entity\PartyType;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\MySqlPartyRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlStockRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Repository\OrderSearchCriteria;
use App\Repository\PdoTransactionManager;
use App\Service\PurchaseOrderService;
use App\Service\StockService;
use DateTimeImmutable;

/**
 * Goods receipt end-to-end di MySQL: PO dibuat, dipesan, diterima sebagian lalu
 * penuh; stok bertambah, ledger Receipt tertulis, status berubah (PO-01).
 */
final class PurchaseOrderReceiptTest extends IntegrationTestCase
{
    private MySqlPurchaseOrderRepository $orders;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = new MySqlPurchaseOrderRepository($this->pdo);
        $stock = new MySqlStockRepository($this->pdo);
        $this->service = new PurchaseOrderService(
            $this->orders,
            new MySqlPartyRepository($this->pdo, PartyType::Supplier),
            new MySqlWarehouseRepository($this->pdo),
            new MySqlProductRepository($this->pdo),
            $stock,
            new StockService($stock),
            new PdoTransactionManager($this->pdo),
            new DateTimeImmutable('today'),
        );
    }

    public function testPartialThenFullReceiptUpdatesStockLedgerAndStatus(): void
    {
        $id = $this->orders->create(1, 2, '2026-10-01', [new NewOrderLine('SKU-0006', 8, 1500000)]);
        $this->service->markOrdered($id);
        $order = $this->orders->findById($id);
        self::assertNotNull($order);
        self::assertMatchesRegularExpression('/^PO-2026-\d{4}$/', $order->orderNo);
        $itemId = $order->items[0]->id;
        $stockBefore = $this->stock('SKU-0006', 2);

        $partial = $this->service->receive($id, [$itemId => '5'], $this->staff());
        self::assertSame(PurchaseOrderStatus::PartiallyReceived, $partial->status);
        self::assertSame(3, $partial->items[0]->remainingQty());
        self::assertSame($stockBefore + 5, $this->stock('SKU-0006', 2));

        $full = $this->service->receive($id, [$itemId => '3'], $this->staff());
        self::assertSame(PurchaseOrderStatus::Received, $full->status);
        self::assertSame($stockBefore + 8, $this->stock('SKU-0006', 2));

        $receipts = $this->service->receipts($full);
        self::assertSame([5, 3], array_map(static fn ($m): int => $m->quantity, $receipts));
        self::assertSame(['Receipt', 'Receipt'], array_map(static fn ($m): string => $m->type, $receipts));
        self::assertSame($this->stock('SKU-0006', 2), $this->ledgerSum('SKU-0006', 2));
    }

    public function testOrderNumbersAreUniqueAndSearchFindsBySupplierName(): void
    {
        $first = $this->orders->findById($this->orders->create(1, 1, '2026-10-02', [new NewOrderLine('SKU-0001', 1, 1)]));
        $second = $this->orders->findById($this->orders->create(1, 1, '2026-10-02', [new NewOrderLine('SKU-0001', 1, 1)]));
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->orderNo, $second->orderNo);

        $result = $this->orders->search(new OrderSearchCriteria(search: 'Elektronik Jaya', status: 'Draft'));
        $numbers = array_map(static fn ($o): string => $o->orderNo, $result->items);
        self::assertContains($first->orderNo, $numbers);
        self::assertContains($second->orderNo, $numbers);
    }

    public function testOrderListPaginatesTenPerPageAndSortsByDate(): void
    {
        $desc = $this->orders->search(new OrderSearchCriteria(sort: 'date_desc'));
        $asc = $this->orders->search(new OrderSearchCriteria(sort: 'date_asc', page: 2));

        self::assertSame(12, $desc->total);
        self::assertCount(10, $desc->items);
        self::assertCount(2, $asc->items);
        self::assertGreaterThanOrEqual($desc->items[1]->orderDate, $desc->items[0]->orderDate);
    }

    private function staff(): User
    {
        return new User(4, 'Rudi Hartono', 'rudi@ioms.test', 'x', Role::WarehouseStaff, true);
    }

    private function stock(string $sku, int $warehouseId): int
    {
        $stmt = $this->pdo->prepare('SELECT quantity FROM product_stock WHERE product_sku = :sku AND warehouse_id = :w');
        $stmt->execute(['sku' => $sku, 'w' => $warehouseId]);

        return (int) $stmt->fetchColumn();
    }

    private function ledgerSum(string $sku, int $warehouseId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM stock_ledger WHERE product_sku = :sku AND warehouse_id = :w');
        $stmt->execute(['sku' => $sku, 'w' => $warehouseId]);

        return (int) $stmt->fetchColumn();
    }
}
