<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlStockRepository;
use App\Repository\MySqlStockTransferRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Repository\OrderSearchCriteria;
use App\Repository\PdoTransactionManager;
use App\Service\BusinessRuleException;
use App\Service\StockService;
use App\Service\StockTransferService;

/**
 * Transfer antar-gudang (K-08) di MySQL. Seed: SKU-0004 Jakarta 5, Surabaya 3.
 */
final class StockTransferTest extends IntegrationTestCase
{
    private StockTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $stock = new MySqlStockRepository($this->pdo);
        $this->service = new StockTransferService(
            new MySqlStockTransferRepository($this->pdo),
            new MySqlWarehouseRepository($this->pdo),
            new MySqlProductRepository($this->pdo),
            $stock,
            new StockService($stock),
            new PdoTransactionManager($this->pdo),
        );
    }

    public function testTransferMovesStockKeepsTotalsAndLedgerConsistent(): void
    {
        $totalBefore = $this->scalar('SELECT SUM(quantity) FROM product_stock');

        $id = $this->service->create(['from_warehouse_id' => '1', 'to_warehouse_id' => '2', 'note' => 'uji'], [['sku' => 'SKU-0004', 'qty' => '4']], $this->staff());

        self::assertSame(1, $this->stock('SKU-0004', 1));
        self::assertSame(7, $this->stock('SKU-0004', 2));
        self::assertSame($totalBefore, $this->scalar('SELECT SUM(quantity) FROM product_stock'), 'total semua gudang tidak berubah');
        // Setiap baris stok tetap sama dengan SUM(ledger) (§1.3).
        self::assertSame(0, $this->scalar(
            'SELECT COUNT(*) FROM product_stock ps
             LEFT JOIN (SELECT product_sku, warehouse_id, SUM(quantity) s FROM stock_ledger GROUP BY product_sku, warehouse_id) l
               ON l.product_sku = ps.product_sku AND l.warehouse_id = ps.warehouse_id
             WHERE ps.quantity <> COALESCE(l.s, 0)'
        ));

        $transfer = $this->service->find($id);
        self::assertNotNull($transfer);
        self::assertMatchesRegularExpression('/^TRF-\d{4}-\d{4}$/', $transfer->transferNo);
        self::assertSame('Rudi Hartono', $transfer->createdByName);
        $movements = array_map(static fn ($m): string => $m->type . ' ' . $m->quantity . ' @' . $m->warehouseName, $this->service->movements($transfer));
        self::assertSame(['Issue -4 @Gudang Jakarta', 'Receipt 4 @Gudang Surabaya'], $movements);
        self::assertSame(1, $this->service->search(new OrderSearchCriteria(search: $transfer->transferNo))->total);
    }

    public function testInsufficientStockRollsBackDocumentAndStock(): void
    {
        $transfersBefore = $this->scalar('SELECT COUNT(*) FROM stock_transfers');
        $ledgerBefore = $this->scalar('SELECT COUNT(*) FROM stock_ledger');

        try {
            $this->service->create(['from_warehouse_id' => '1', 'to_warehouse_id' => '2'], [['sku' => 'SKU-0004', 'qty' => '6']], $this->staff());
            self::fail('Transfer melebihi stok seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            self::assertStringContainsString('tersedia 5, dibutuhkan 6', $e->getMessage());
        }

        self::assertSame($transfersBefore, $this->scalar('SELECT COUNT(*) FROM stock_transfers'), 'dokumen transfer ikut dibatalkan');
        self::assertSame($ledgerBefore, $this->scalar('SELECT COUNT(*) FROM stock_ledger'));
        self::assertSame(5, $this->stock('SKU-0004', 1));
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

    private function scalar(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }
}
