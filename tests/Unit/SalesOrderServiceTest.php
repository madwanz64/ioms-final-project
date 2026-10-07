<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Party;
use App\Entity\Product;
use App\Entity\Role;
use App\Entity\SalesOrderStatus;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\OrderSearchCriteria;
use App\Service\AuthorizationException;
use App\Service\BusinessRuleException;
use App\Service\NotFoundException;
use App\Service\SalesOrderPolicy;
use App\Service\SalesOrderService;
use App\Service\StockService;
use App\Service\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fake\ImmediateTransactionManager;
use Tests\Fake\InMemoryPartyRepository;
use Tests\Fake\InMemoryProductRepository;
use Tests\Fake\InMemorySalesOrderRepository;
use Tests\Fake\InMemoryStockRepository;
use Tests\Fake\InMemoryWarehouseRepository;

/**
 * Area logic: transisi status SO, otorisasi approve di server, goods issue.
 */
final class SalesOrderServiceTest extends TestCase
{
    private InMemorySalesOrderRepository $orders;
    private InMemoryStockRepository $stock;
    private SalesOrderService $service;
    private User $admin;
    private User $sinta;
    private User $doni;
    private User $rudi;

    protected function setUp(): void
    {
        $this->orders = new InMemorySalesOrderRepository();
        $customers = new InMemoryPartyRepository();
        $customers->add(new Party(1, 'PT Pelanggan', '021', 'Jakarta', true));
        $warehouses = new InMemoryWarehouseRepository();
        $warehouses->add(new Warehouse(1, 'Gudang Jakarta', 'Jakarta', true));
        $products = new InMemoryProductRepository([1]);
        $products->seed(new Product('SKU-A', 'Produk A', 1, 'pcs', 1000, 2500, 5, null, true), [1 => 5]);
        $this->stock = new InMemoryStockRepository();
        $this->stock->set('SKU-A', 1, 5);

        $this->admin = new User(1, 'Admin', 'admin@ioms.test', 'x', Role::Admin, true);
        $this->sinta = new User(2, 'Sinta', 'sinta@ioms.test', 'x', Role::Sales, true);
        $this->doni = new User(3, 'Doni', 'doni@ioms.test', 'x', Role::Sales, true);
        $this->rudi = new User(4, 'Rudi', 'rudi@ioms.test', 'x', Role::WarehouseStaff, true);

        $this->service = new SalesOrderService(
            $this->orders,
            $customers,
            $warehouses,
            $products,
            $this->stock,
            new StockService($this->stock),
            new ImmediateTransactionManager(),
            new SalesOrderPolicy(),
            new DateTimeImmutable('2026-10-07'),
        );
    }

    public function testCreateUsesCatalogPriceAndRecordsCreator(): void
    {
        $id = $this->createOrder($this->sinta, 3, ['price' => '1']);

        $order = $this->orders->findById($id);
        self::assertNotNull($order);
        self::assertSame(SalesOrderStatus::Draft, $order->status);
        self::assertSame($this->sinta->id, $order->createdBy);
        self::assertSame(2500, $order->items[0]->price, 'Harga dari katalog, bukan dari input pengguna');
    }

    public function testCreateRejectsQtyAboveAvailableStockInChosenWarehouse(): void
    {
        try {
            $this->createOrder($this->sinta, 6);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame('Stok tersedia di Gudang Jakarta hanya 5.', $e->errors['items.0.qty']);
        }
    }

    public function testWarehouseStaffCannotCreateSalesOrder(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->createOrder($this->rudi, 1);
    }

    public function testFullFlowDraftToFulfilledReducesStockAndWritesIssueLedger(): void
    {
        $id = $this->createOrder($this->sinta, 3);

        self::assertSame(SalesOrderStatus::PendingApproval, $this->service->submit($id, $this->sinta)->status);
        $approved = $this->service->approve($id, $this->admin);
        self::assertSame(SalesOrderStatus::Approved, $approved->status);
        self::assertSame($this->admin->id, $approved->approvedBy);
        self::assertSame(SalesOrderStatus::Fulfilled, $this->service->fulfill($id, $this->rudi)->status);

        self::assertSame(2, $this->stock->quantity('SKU-A', 1));
        self::assertCount(1, $this->stock->ledger);
        self::assertSame(-3, $this->stock->ledger[0]->delta);
        self::assertSame('Issue', $this->stock->ledger[0]->movementType);
        self::assertSame($this->rudi->id, $this->stock->ledger[0]->performedBy);
    }

    public function testSalesCannotApproveOwnOrderEvenByCallingTheServiceDirectly(): void
    {
        $id = $this->createOrder($this->sinta, 1);
        $this->service->submit($id, $this->sinta);

        try {
            $this->service->approve($id, $this->sinta);
            self::fail('AuthorizationException seharusnya dilempar.');
        } catch (AuthorizationException $e) {
            self::assertSame('Anda tidak dapat menyetujui Sales Order yang Anda buat sendiri.', $e->getMessage());
        }
        self::assertSame(SalesOrderStatus::PendingApproval, $this->orders->findById($id)?->status);
    }

    public function testAdminCanApproveOrderTheyCreated(): void
    {
        // K-05 (dikonfirmasi): §1.2 membolehkan Admin membuat dan menyetujui SO.
        $id = $this->createOrder($this->admin, 1);
        $this->service->submit($id, $this->admin);

        $approved = $this->service->approve($id, $this->admin);

        self::assertSame(SalesOrderStatus::Approved, $approved->status);
        self::assertSame($this->admin->id, $approved->approvedBy);
    }

    public function testOtherSalesCannotEvenSeeTheOrderToApproveIt(): void
    {
        $id = $this->createOrder($this->sinta, 1);
        $this->service->submit($id, $this->sinta);

        $this->expectException(NotFoundException::class);
        $this->service->approve($id, $this->doni);
    }

    public function testRejectCancelsPendingOrder(): void
    {
        $id = $this->createOrder($this->sinta, 1);
        $this->service->submit($id, $this->sinta);

        self::assertSame(SalesOrderStatus::Cancelled, $this->service->reject($id, $this->admin)->status);
    }

    public function testGoodsIssueIsRejectedWhenStockIsInsufficientAndOrderStaysApproved(): void
    {
        $first = $this->approvedOrder(4);
        $second = $this->approvedOrder(4); // total 8 > stok 5; keduanya lolos saat dibuat

        $this->service->fulfill($first, $this->rudi);
        try {
            $this->service->fulfill($second, $this->rudi);
            self::fail('Goods issue kedua seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            self::assertSame('Stok SKU-A tidak mencukupi: tersedia 1, dibutuhkan 4.', $e->getMessage());
        }

        self::assertSame(1, $this->stock->quantity('SKU-A', 1));
        self::assertSame(SalesOrderStatus::Approved, $this->orders->findById($second)?->status);
        self::assertCount(1, $this->stock->ledger);
    }

    public function testGoodsIssueOnlyForApprovedOrders(): void
    {
        $id = $this->createOrder($this->sinta, 1);
        $this->service->submit($id, $this->sinta);

        $this->expectException(BusinessRuleException::class);
        $this->service->fulfill($id, $this->rudi);
    }

    public function testFulfilledOrderCannotBeCancelled(): void
    {
        $id = $this->approvedOrder(1);
        $this->service->fulfill($id, $this->rudi);

        $this->expectException(BusinessRuleException::class);
        $this->service->cancel($id, $this->admin);
    }

    public function testListIsScopedPerRole(): void
    {
        $sintaDraft = $this->createOrder($this->sinta, 1);
        $doniPending = $this->createOrder($this->doni, 1);
        $this->service->submit($doniPending, $this->doni);

        $ids = fn (User $u): array => array_map(static fn ($o): int => $o->id, $this->service->search(new OrderSearchCriteria(), $u)->items);

        self::assertSame([$sintaDraft], $ids($this->sinta));
        self::assertSame([$doniPending], $ids($this->rudi), 'Warehouse tidak melihat Draft');
        self::assertSame([$sintaDraft, $doniPending], $ids($this->admin));
    }

    /**
     * @param array<string, string> $extra
     */
    private function createOrder(User $actor, int $qty, array $extra = []): int
    {
        return $this->service->create(
            ['customer_id' => '1', 'warehouse_id' => '1', 'order_date' => '2026-10-07'],
            [['sku' => 'SKU-A', 'qty' => (string) $qty] + $extra],
            $actor,
        );
    }

    private function approvedOrder(int $qty): int
    {
        $id = $this->createOrder($this->sinta, $qty);
        $this->service->submit($id, $this->sinta);
        $this->service->approve($id, $this->admin);

        return $id;
    }
}
