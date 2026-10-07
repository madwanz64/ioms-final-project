<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Party;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\BusinessRuleException;
use App\Service\PurchaseOrderService;
use App\Service\StockService;
use App\Service\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\ImmediateTransactionManager;
use Tests\Fake\InMemoryPartyRepository;
use Tests\Fake\InMemoryProductRepository;
use Tests\Fake\InMemoryPurchaseOrderRepository;
use Tests\Fake\InMemoryStockRepository;
use Tests\Fake\InMemoryWarehouseRepository;

/**
 * Area logic: validasi PO (termasuk tanggal), transisi status PO, goods receipt.
 */
final class PurchaseOrderServiceTest extends TestCase
{
    private InMemoryPurchaseOrderRepository $orders;
    private InMemoryStockRepository $stock;
    private ImmediateTransactionManager $transactions;
    private PurchaseOrderService $service;
    private User $staff;

    protected function setUp(): void
    {
        $this->orders = new InMemoryPurchaseOrderRepository();
        $suppliers = new InMemoryPartyRepository();
        $suppliers->add(new Party(1, 'CV Aktif', '021', 'Jakarta', true));
        $suppliers->add(new Party(2, 'CV Nonaktif', '021', 'Jakarta', false));
        $warehouses = new InMemoryWarehouseRepository();
        $warehouses->add(new Warehouse(1, 'Gudang Jakarta', 'Jakarta', true));
        $products = new InMemoryProductRepository();
        $products->seed(new Product('SKU-A', 'Produk A', 1, 'pcs', 1000, 2000, 5, null, true));
        $products->seed(new Product('SKU-B', 'Produk B', 1, 'pcs', 1000, 2000, 5, null, true));
        $products->seed(new Product('SKU-X', 'Produk Nonaktif', 1, 'pcs', 1000, 2000, 5, null, false));
        $this->stock = new InMemoryStockRepository();
        $this->stock->set('SKU-A', 1, 1);
        $this->stock->set('SKU-B', 1, 0);
        $this->transactions = new ImmediateTransactionManager();
        $this->staff = new User(4, 'Rudi', 'rudi@ioms.test', 'x', Role::WarehouseStaff, true);

        $this->service = new PurchaseOrderService(
            $this->orders,
            $suppliers,
            $warehouses,
            $products,
            $this->stock,
            new StockService($this->stock),
            $this->transactions,
            new DateTimeImmutable('2026-10-07'),
        );
    }

    public function testCreateValidDraftIgnoresBlankRowsAndNormalisesSku(): void
    {
        $this->service->create($this->header(), [['sku' => 'sku-a', 'qty' => '10', 'buy_price' => '900'], ['sku' => '', 'qty' => '', 'buy_price' => '']], $this->staff);

        self::assertCount(1, $this->orders->created);
        self::assertSame(4, $this->orders->created[0]['createdBy'], 'pembuat PO tercatat (K-03)');
        $lines = $this->orders->created[0]['lines'];
        self::assertCount(1, $lines);
        self::assertSame('SKU-A', $lines[0]->sku);
        self::assertSame(10, $lines[0]->qty);
        self::assertSame(900, $lines[0]->price);
    }

    #[DataProvider('invalidDates')]
    public function testOrderDateValidation(string $date, string $message): void
    {
        $errors = $this->createErrors(['order_date' => $date]);

        self::assertSame($message, $errors['order_date']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidDates(): array
    {
        return [
            'kosong' => ['', 'Tanggal order wajib diisi.'],
            'besok (masa depan)' => ['2026-10-08', 'Tanggal order tidak boleh di masa depan.'],
            'tanggal tidak ada di kalender' => ['2026-02-30', 'Tanggal order tidak valid (format YYYY-MM-DD).'],
            'format lain' => ['07/10/2026', 'Tanggal order tidak valid (format YYYY-MM-DD).'],
        ];
    }

    public function testTodayIsAValidOrderDate(): void
    {
        $this->service->create($this->header(['order_date' => '2026-10-07']), [['sku' => 'SKU-A', 'qty' => '1', 'buy_price' => '0']], $this->staff);

        self::assertCount(1, $this->orders->created);
    }

    public function testLineAndHeaderRulesAreCollectedTogetherAndNothingIsSaved(): void
    {
        $errors = $this->createErrors(['supplier_id' => '2'], [
            ['sku' => 'SKU-X', 'qty' => '1', 'buy_price' => '1'],
            ['sku' => 'SKU-A', 'qty' => '0', 'buy_price' => '1'],
            ['sku' => 'SKU-A', 'qty' => '2', 'buy_price' => '-5'],
        ]);

        self::assertSame('Pilih supplier yang aktif.', $errors['supplier_id']);
        self::assertSame('Pilih produk yang aktif.', $errors['items.0.sku']);
        self::assertSame('Qty minimal 1.', $errors['items.1.qty']);
        self::assertSame('Produk ini sudah ada di baris lain; gabungkan qty-nya.', $errors['items.2.sku']);
        self::assertArrayHasKey('items.2.buy_price', $errors);
        self::assertSame([], $this->orders->created);
    }

    public function testAtLeastOneItemIsRequired(): void
    {
        $errors = $this->createErrors([], [['sku' => '', 'qty' => '', 'buy_price' => '']]);

        self::assertSame('Tambahkan minimal satu item.', $errors['items']);
    }

    public function testPartialReceiptAddsStockKeepsRemainderAndMarksPartiallyReceived(): void
    {
        $this->orders->add($this->order(PurchaseOrderStatus::Ordered));

        $updated = $this->service->receive(1, [11 => '4', 12 => ''], $this->staff);

        self::assertSame(PurchaseOrderStatus::PartiallyReceived, $updated->status);
        self::assertSame(6, $updated->item(11)?->remainingQty());
        self::assertSame(5, $this->stock->quantity('SKU-A', 1));
        self::assertCount(1, $this->stock->ledger);
        self::assertSame(4, $this->stock->ledger[0]->delta);
        self::assertSame('PO-2026-0099', $this->stock->ledger[0]->refId);
        self::assertSame(4, $this->stock->ledger[0]->performedBy);
        self::assertSame(1, $this->transactions->runs);
    }

    public function testReceivingAllRemainingQtyMarksReceived(): void
    {
        $this->orders->add($this->order(PurchaseOrderStatus::PartiallyReceived, receivedA: 4));

        $updated = $this->service->receive(1, [11 => '6', 12 => '3'], $this->staff);

        self::assertSame(PurchaseOrderStatus::Received, $updated->status);
        self::assertSame(3, $this->stock->quantity('SKU-B', 1));
    }

    public function testReceivingMoreThanRemainingIsRejectedAndStockUntouched(): void
    {
        $this->orders->add($this->order(PurchaseOrderStatus::Ordered, receivedA: 8));

        try {
            $this->service->receive(1, [11 => '3'], $this->staff);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame('Maksimal 2 (sisa yang belum diterima).', $e->errors['receive.11']);
        }
        self::assertSame(1, $this->stock->quantity('SKU-A', 1));
        self::assertSame([], $this->stock->ledger);
    }

    #[DataProvider('statusesThatCannotReceive')]
    public function testReceiptIsRejectedForStatusesOtherThanOrderedOrPartial(PurchaseOrderStatus $status): void
    {
        $this->orders->add($this->order($status));

        $this->expectException(BusinessRuleException::class);
        $this->service->receive(1, [11 => '1'], $this->staff);
    }

    /**
     * @return array<string, array{PurchaseOrderStatus}>
     */
    public static function statusesThatCannotReceive(): array
    {
        return ['draft' => [PurchaseOrderStatus::Draft], 'received' => [PurchaseOrderStatus::Received], 'cancelled' => [PurchaseOrderStatus::Cancelled]];
    }

    public function testStatusTransitions(): void
    {
        $this->orders->add($this->order(PurchaseOrderStatus::Draft));
        self::assertSame(PurchaseOrderStatus::Ordered, $this->service->markOrdered(1)->status);
        self::assertSame(PurchaseOrderStatus::Cancelled, $this->service->cancel(1)->status);

        $this->expectException(BusinessRuleException::class);
        $this->service->markOrdered(1); // Cancelled tidak bisa dipesan lagi
    }

    public function testPartiallyReceivedOrderCannotBeCancelled(): void
    {
        $this->orders->add($this->order(PurchaseOrderStatus::PartiallyReceived, receivedA: 1));

        $this->expectException(BusinessRuleException::class);
        $this->service->cancel(1);
    }

    /**
     * @param array<string, string> $header
     * @param list<array<string, string>>|null $lines
     * @return array<string, string>
     */
    private function createErrors(array $header, ?array $lines = null): array
    {
        try {
            $this->service->create($this->header($header), $lines ?? [['sku' => 'SKU-A', 'qty' => '1', 'buy_price' => '1']], $this->staff);
        } catch (ValidationException $e) {
            return $e->errors;
        }
        self::fail('ValidationException seharusnya dilempar.');
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function header(array $overrides = []): array
    {
        return $overrides + ['supplier_id' => '1', 'warehouse_id' => '1', 'order_date' => '2026-10-01'];
    }

    private function order(PurchaseOrderStatus $status, int $receivedA = 0): PurchaseOrder
    {
        return new PurchaseOrder(1, 'PO-2026-0099', 1, 'CV Aktif', 1, 'Gudang Jakarta', $status, '2026-10-01', [
            new PurchaseOrderItem(11, 'SKU-A', 'Produk A', 'pcs', 10, $receivedA, 1000),
            new PurchaseOrderItem(12, 'SKU-B', 'Produk B', 'pcs', 3, 0, 1000),
        ], 4, 'Rudi');
    }
}
