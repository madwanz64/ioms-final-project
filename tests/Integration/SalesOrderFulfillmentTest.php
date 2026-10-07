<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\PartyType;
use App\Entity\Role;
use App\Entity\SalesOrderStatus;
use App\Entity\User;
use App\Repository\MySqlPartyRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Repository\OrderSearchCriteria;
use App\Repository\PdoTransactionManager;
use App\Service\AuthorizationException;
use App\Service\BusinessRuleException;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use App\Service\StockService;
use DateTimeImmutable;

/**
 * SO-01 end-to-end di MySQL. Seed: SKU-0006 di gudang 1 (Jakarta) = 2 unit.
 */
final class SalesOrderFulfillmentTest extends IntegrationTestCase
{
    private MySqlSalesOrderRepository $orders;
    private SalesOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = new MySqlSalesOrderRepository($this->pdo);
        $stock = new MySqlStockRepository($this->pdo);
        $this->service = new SalesOrderService(
            $this->orders,
            new MySqlPartyRepository($this->pdo, PartyType::Customer),
            new MySqlWarehouseRepository($this->pdo),
            new MySqlProductRepository($this->pdo),
            $stock,
            new StockService($stock),
            new PdoTransactionManager($this->pdo),
            new SalesOrderPolicy(),
            new DateTimeImmutable('today'),
        );
    }

    public function testSecondGoodsIssueIsRejectedAfterFirstUsesUpTheStock(): void
    {
        $first = $this->approvedOrder(2);
        $second = $this->approvedOrder(2);

        $fulfilled = $this->service->fulfill($first, $this->user(4, Role::WarehouseStaff));
        self::assertSame(SalesOrderStatus::Fulfilled, $fulfilled->status);

        try {
            $this->service->fulfill($second, $this->user(4, Role::WarehouseStaff));
            self::fail('Goods issue kedua seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            self::assertStringContainsString('tersedia 0, dibutuhkan 2', $e->getMessage());
        }

        $order = $this->orders->findById($second);
        self::assertNotNull($order);
        self::assertSame(SalesOrderStatus::Approved, $order->status, 'Rollback: status tidak berubah');
        self::assertSame(0, $this->scalar("SELECT quantity FROM product_stock WHERE product_sku = 'SKU-0006' AND warehouse_id = 1"));
        self::assertSame(0, $this->scalar("SELECT SUM(quantity) FROM stock_ledger WHERE product_sku = 'SKU-0006' AND warehouse_id = 1"));
        $ledger = $this->pdo->prepare('SELECT COUNT(*) FROM stock_ledger WHERE ref_id = :ref');
        $ledger->execute(['ref' => $order->orderNo]);
        self::assertSame(0, (int) $ledger->fetchColumn(), 'Tidak ada ledger untuk goods issue yang ditolak');
    }

    public function testSalesCannotApproveOwnOrderAndApprovalRecordsApprover(): void
    {
        $sinta = $this->user(2, Role::Sales);
        $id = $this->service->create($this->header(), [['sku' => 'SKU-0010', 'qty' => '1', 'price' => '55000']], $sinta);
        $this->service->submit($id, $sinta);

        try {
            $this->service->approve($id, $sinta);
            self::fail('Sales seharusnya tidak bisa menyetujui order sendiri.');
        } catch (AuthorizationException) {
            self::assertSame(SalesOrderStatus::PendingApproval, $this->orders->findById($id)?->status);
        }

        $approved = $this->service->approve($id, $this->user(1, Role::Admin));
        self::assertSame('Budi Santoso', $approved->approvedByName);
        self::assertMatchesRegularExpression('/^SO-\d{4}-\d{4}$/', $approved->orderNo);
    }

    public function testListScopingIsAppliedInTheQuery(): void
    {
        $sinta = $this->service->search(new OrderSearchCriteria(perPage: 100), $this->user(2, Role::Sales));
        $warehouse = $this->service->search(new OrderSearchCriteria(perPage: 100), $this->user(4, Role::WarehouseStaff));

        self::assertSame($this->scalar('SELECT COUNT(*) FROM sales_orders WHERE created_by = 2'), $sinta->total);
        self::assertSame($this->scalar("SELECT COUNT(*) FROM sales_orders WHERE status <> 'Draft'"), $warehouse->total);
        self::assertNotContains('Draft', array_map(static fn ($o): string => $o->status, $warehouse->items));
    }

    private function approvedOrder(int $qty): int
    {
        $sinta = $this->user(2, Role::Sales);
        $id = $this->service->create($this->header(), [['sku' => 'SKU-0006', 'qty' => (string) $qty, 'price' => '1850000']], $sinta);
        $this->service->submit($id, $sinta);
        $this->service->approve($id, $this->user(1, Role::Admin));

        return $id;
    }

    /**
     * @return array<string, string>
     */
    private function header(): array
    {
        return ['customer_id' => '1', 'warehouse_id' => '1', 'order_date' => (new DateTimeImmutable('today'))->format('Y-m-d')];
    }

    private function user(int $id, Role $role): User
    {
        return new User($id, 'User ' . $id, $id . '@ioms.test', 'x', $role, true);
    }

    private function scalar(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }
}
