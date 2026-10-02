<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Repository\MySqlProductRepository;
use App\Repository\ProductSearchCriteria;

final class MySqlProductRepositoryTest extends IntegrationTestCase
{
    private MySqlProductRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MySqlProductRepository($this->pdo);
    }

    public function testLowStockFilterUsesTotalAcrossWarehouses(): void
    {
        $result = $this->repo->search(new ProductSearchCriteria(stockStatus: 'low', perPage: 50));

        self::assertSame(['SKU-0001', 'SKU-0002', 'SKU-0004', 'SKU-0006', 'SKU-0009'], $this->skus($result->items, sort: true));
        foreach ($result->items as $item) {
            self::assertTrue($item->isLowStock());
        }
    }

    public function testPaginationTenPerPageAndOutOfRangePageIsClamped(): void
    {
        $page1 = $this->repo->search(new ProductSearchCriteria());
        $page99 = $this->repo->search(new ProductSearchCriteria(page: 99));

        self::assertSame(32, $page1->total);
        self::assertSame(4, $page1->totalPages());
        self::assertCount(10, $page1->items);
        self::assertSame(4, $page99->page);
        self::assertCount(2, $page99->items);
    }

    public function testSearchCategoryAndSortCombineAcrossPages(): void
    {
        // Kategori 3 = Aksesoris (seed).
        $result = $this->repo->search(new ProductSearchCriteria(search: 'kabel', categoryId: 3, sort: 'name_desc'));
        $otherCategory = $this->repo->search(new ProductSearchCriteria(search: 'kabel', categoryId: 1));

        self::assertSame(['SKU-0014', 'SKU-0001'], $this->skus($result->items)); // "Kabel LAN" > "Kabel HDMI"
        self::assertSame(0, $otherCategory->total);
    }

    public function testLikeWildcardsInSearchAreTreatedLiterally(): void
    {
        self::assertSame(0, $this->repo->search(new ProductSearchCriteria(search: '%'))->total);
        self::assertSame(0, $this->repo->search(new ProductSearchCriteria(search: '_'))->total);
    }

    public function testCreateInsertsProductWithZeroStockRowPerWarehouse(): void
    {
        $this->repo->create(new Product('INT-0001', 'Produk Integrasi', 1, 'pcs', 1000, 1500, 5, null, true));

        $stored = $this->repo->findBySku('INT-0001');
        self::assertNotNull($stored);
        self::assertSame(1500, $stored->sellPrice);
        $levels = $this->repo->stockLevels('INT-0001');
        self::assertCount(2, $levels);
        self::assertSame([0, 0], array_map(static fn ($level): int => $level->quantity, $levels));
    }

    public function testUpdatePersistsChangesAndDeactivation(): void
    {
        $product = $this->repo->findBySku('SKU-0001');
        self::assertNotNull($product);

        $this->repo->update(new Product($product->sku, 'Kabel HDMI 3m', $product->categoryId, $product->unit, $product->buyPrice, 47000, 12, null, false));

        $reloaded = $this->repo->findBySku('SKU-0001');
        self::assertNotNull($reloaded);
        self::assertSame('Kabel HDMI 3m', $reloaded->name);
        self::assertSame(47000, $reloaded->sellPrice);
        self::assertFalse($reloaded->active);
        self::assertNotContains('SKU-0001', $this->skus($this->repo->search((new ProductSearchCriteria(perPage: 50))->withOnlyActive())->items));
    }

    public function testStockPerWarehouseMatchesLedgerHistory(): void
    {
        $levels = $this->repo->stockLevels('SKU-0001');
        $byWarehouse = [];
        foreach ($levels as $level) {
            $byWarehouse[$level->warehouseName] = $level->quantity;
        }

        self::assertSame(['Gudang Jakarta' => 3, 'Gudang Surabaya' => 1], $byWarehouse);
        // Seed konsisten: SUM(ledger) per gudang sama dengan product_stock (§1.3).
        $stmt = $this->pdo->query("SELECT warehouse_id, SUM(quantity) FROM stock_ledger WHERE product_sku = 'SKU-0001' GROUP BY warehouse_id ORDER BY warehouse_id");
        self::assertNotFalse($stmt);
        self::assertSame([1 => 3, 2 => 1], array_map('intval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR)));
    }

    /**
     * @param list<ProductSummary> $items
     * @return list<string>
     */
    private function skus(array $items, bool $sort = false): array
    {
        $skus = array_map(static fn (ProductSummary $item): string => $item->sku, $items);
        if ($sort) {
            sort($skus);
        }

        return $skus;
    }
}
