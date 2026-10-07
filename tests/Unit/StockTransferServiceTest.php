<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\BusinessRuleException;
use App\Service\StockService;
use App\Service\StockTransferService;
use App\Service\ValidationException;
use PHPUnit\Framework\TestCase;
use Tests\Fake\ImmediateTransactionManager;
use Tests\Fake\InMemoryProductRepository;
use Tests\Fake\InMemoryStockRepository;
use Tests\Fake\InMemoryStockTransferRepository;
use Tests\Fake\InMemoryWarehouseRepository;

/**
 * Area logic: perpindahan stok antar-gudang (K-08).
 */
final class StockTransferServiceTest extends TestCase
{
    private InMemoryStockRepository $stock;
    private InMemoryStockTransferRepository $transfers;
    private ImmediateTransactionManager $transactions;
    private StockTransferService $service;
    private User $rudi;

    protected function setUp(): void
    {
        $warehouses = new InMemoryWarehouseRepository();
        $warehouses->add(new Warehouse(1, 'Gudang Jakarta', 'Jakarta', true));
        $warehouses->add(new Warehouse(2, 'Gudang Surabaya', 'Surabaya', true));
        $warehouses->add(new Warehouse(3, 'Gudang Lama', 'Medan', false));
        $products = new InMemoryProductRepository([1, 2]);
        $products->seed(new Product('SKU-A', 'Produk A', 1, 'pcs', 1000, 2000, 5, null, true), [1 => 10, 2 => 1]);
        $products->seed(new Product('SKU-B', 'Produk B', 1, 'pcs', 1000, 2000, 5, null, true), [1 => 2, 2 => 0]);
        $this->stock = new InMemoryStockRepository();
        $this->stock->set('SKU-A', 1, 10);
        $this->stock->set('SKU-A', 2, 1);
        $this->stock->set('SKU-B', 1, 2);
        $this->stock->set('SKU-B', 2, 0);
        $this->transfers = new InMemoryStockTransferRepository();
        $this->transactions = new ImmediateTransactionManager();
        $this->rudi = new User(4, 'Rudi', 'rudi@ioms.test', 'x', Role::WarehouseStaff, true);

        $this->service = new StockTransferService($this->transfers, $warehouses, $products, $this->stock, new StockService($this->stock), $this->transactions);
    }

    public function testTransferMovesStockAndWritesIssuePlusReceiptPerItem(): void
    {
        $id = $this->service->create(
            ['from_warehouse_id' => '1', 'to_warehouse_id' => '2', 'note' => 'Isi ulang Surabaya'],
            [['sku' => 'SKU-A', 'qty' => '4'], ['sku' => 'SKU-B', 'qty' => '2']],
            $this->rudi,
        );

        self::assertSame(6, $this->stock->quantity('SKU-A', 1));
        self::assertSame(5, $this->stock->quantity('SKU-A', 2));
        self::assertSame(0, $this->stock->quantity('SKU-B', 1));
        self::assertSame(2, $this->stock->quantity('SKU-B', 2));

        $ledger = array_map(
            static fn ($c): string => sprintf('%s@%d %s %+d %s/%s by %d', $c->sku, $c->warehouseId, $c->movementType, $c->delta, $c->refType, $c->refId, $c->performedBy),
            $this->stock->ledger,
        );
        self::assertSame([
            'SKU-A@1 Issue -4 TRF/TRF-TEST-0001 by 4',
            'SKU-A@2 Receipt +4 TRF/TRF-TEST-0001 by 4',
            'SKU-B@1 Issue -2 TRF/TRF-TEST-0001 by 4',
            'SKU-B@2 Receipt +2 TRF/TRF-TEST-0001 by 4',
        ], $ledger);
        self::assertSame('Isi ulang Surabaya', $this->transfers->findById($id)?->note);
        self::assertSame(1, $this->transactions->runs, 'dokumen & stok dalam satu transaksi');
    }

    public function testTotalStockAcrossWarehousesIsUnchanged(): void
    {
        $before = array_sum($this->stock->quantities);

        $this->service->create(['from_warehouse_id' => '1', 'to_warehouse_id' => '2'], [['sku' => 'SKU-A', 'qty' => '7']], $this->rudi);

        self::assertSame($before, array_sum($this->stock->quantities));
    }

    public function testInsufficientStockRejectsTheWholeTransfer(): void
    {
        try {
            // SKU-A cukup (10), SKU-B tidak (2) -> tidak boleh ada perpindahan sebagian.
            $this->service->create(['from_warehouse_id' => '1', 'to_warehouse_id' => '2'], [['sku' => 'SKU-A', 'qty' => '3'], ['sku' => 'SKU-B', 'qty' => '5']], $this->rudi);
            self::fail('BusinessRuleException seharusnya dilempar.');
        } catch (BusinessRuleException $e) {
            self::assertSame('Stok SKU-B tidak mencukupi: tersedia 2, dibutuhkan 5.', $e->getMessage());
        }

        self::assertSame([], $this->stock->ledger);
        self::assertSame(10, $this->stock->quantity('SKU-A', 1));
        self::assertSame(1, $this->stock->quantity('SKU-A', 2));
    }

    public function testSameWarehouseInactiveWarehouseAndEmptyItemsAreRejected(): void
    {
        $errors = $this->errors(['from_warehouse_id' => '1', 'to_warehouse_id' => '1'], [['sku' => 'SKU-A', 'qty' => '1']]);
        self::assertSame('Gudang tujuan harus berbeda dari gudang asal.', $errors['to_warehouse_id']);

        $errors = $this->errors(['from_warehouse_id' => '3', 'to_warehouse_id' => '2'], [['sku' => '', 'qty' => '']]);
        self::assertSame('Pilih gudang asal yang aktif.', $errors['from_warehouse_id']);
        self::assertSame('Tambahkan minimal satu item.', $errors['items']);

        self::assertSame([], $this->stock->ledger);
    }

    /**
     * @param array<string, string> $input
     * @param list<array<string, string>> $lines
     * @return array<string, string>
     */
    private function errors(array $input, array $lines): array
    {
        try {
            $this->service->create($input, $lines, $this->rudi);
        } catch (ValidationException $e) {
            return $e->errors;
        }
        self::fail('ValidationException seharusnya dilempar.');
    }
}
