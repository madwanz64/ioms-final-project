<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Repository\ProductSearchCriteria;
use App\Service\DashboardService;
use App\Service\ReportService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryProductRepository;
use Tests\Fake\InMemoryReportRepository;

/**
 * Area logic: perhitungan low stock (total stok semua gudang < reorder point).
 */
final class LowStockTest extends TestCase
{
    #[DataProvider('boundaries')]
    public function testLowStockBoundary(int $totalStock, int $reorderPoint, bool $expected): void
    {
        $summary = new ProductSummary('SKU-1', 'X', 'Kat', 1000, $reorderPoint, $totalStock, true);

        self::assertSame($expected, $summary->isLowStock());
    }

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function boundaries(): array
    {
        return [
            'di bawah reorder point' => [4, 10, true],
            'tepat sama dengan reorder point bukan low' => [10, 10, false],
            'di atas reorder point' => [11, 10, false],
            'reorder point 0 tidak pernah low' => [0, 0, false],
        ];
    }

    public function testDashboardCountsOnlyActiveLowStockProductsAcrossWarehouses(): void
    {
        $repo = new InMemoryProductRepository();
        // Total 3+1 = 4 < 10 -> low, walau tiap gudang dilihat terpisah juga di bawah 10.
        $repo->seed($this->product('A-001', 10, true), [1 => 3, 2 => 1]);
        // Total 6+6 = 12 >= 10 -> normal, walau gudang 1 saja (6) di bawah 10.
        $repo->seed($this->product('A-002', 10, true), [1 => 6, 2 => 6]);
        // Low tapi nonaktif -> tidak dihitung di dashboard.
        $repo->seed($this->product('A-003', 10, false), [1 => 0, 2 => 0]);

        $today = new DateTimeImmutable('2026-10-07');
        $reports = new InMemoryReportRepository();
        $summary = (new DashboardService($repo, $reports, new ReportService($reports, $today), $today))->stockSummary();

        self::assertSame(2, $summary['activeProducts']);
        self::assertSame(1, $summary['lowStockCount']);
        self::assertSame(['A-001'], array_map(static fn (ProductSummary $p): string => $p->sku, $summary['lowStock']));
    }

    public function testSearchCriteriaWhitelistsUserInput(): void
    {
        $criteria = ProductSearchCriteria::fromQuery([
            'q' => 'kabel',
            'category' => '1 OR 1=1',
            'stock' => 'semua',
            'sort' => 'name; DROP TABLE products',
            'page' => '-3',
        ]);

        self::assertSame('kabel', $criteria->search);
        self::assertNull($criteria->categoryId);
        self::assertSame('', $criteria->stockStatus);
        self::assertSame('name_asc', $criteria->sort);
        self::assertSame(1, $criteria->page);
        self::assertTrue($criteria->hasFilters());
    }

    private function product(string $sku, int $reorderPoint, bool $active): Product
    {
        return new Product($sku, 'Produk ' . $sku, 1, 'pcs', 1000, 2000, $reorderPoint, null, $active);
    }
}
