<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\LowStockLine;
use App\Repository\MySqlProductRepository;
use App\Service\LowStockReportService;

/**
 * JOB-01 pada MySQL: hasil sama dengan query SQL independen atas seed.
 */
final class LowStockReportTest extends IntegrationTestCase
{
    public function testSummaryMatchesIndependentSqlAndIsSortedByShortage(): void
    {
        $lines = (new LowStockReportService(new MySqlProductRepository($this->pdo)))->lines();

        $stmt = $this->pdo->query(
            'SELECT p.sku FROM products p JOIN product_stock ps ON ps.product_sku = p.sku
             WHERE p.active = 1 GROUP BY p.sku, p.reorder_point HAVING SUM(ps.quantity) < p.reorder_point ORDER BY p.sku'
        );
        self::assertNotFalse($stmt);
        $expected = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $actual = array_map(static fn (LowStockLine $l): string => $l->sku, $lines);
        sort($actual);

        self::assertSame($expected, $actual);
        self::assertSame('SKU-0002', $lines[0]->sku, 'Mouse Wireless: stok 2 dari reorder 15 = kekurangan terbesar');
        self::assertSame(13, $lines[0]->shortage());
        self::assertCount(2, $lines[0]->levels);
    }

    public function testDeactivatedProductDisappearsFromSummary(): void
    {
        $this->pdo->exec("UPDATE products SET active = 0 WHERE sku = 'SKU-0002'");

        $skus = array_map(
            static fn (LowStockLine $l): string => $l->sku,
            (new LowStockReportService(new MySqlProductRepository($this->pdo)))->lines(),
        );

        self::assertNotContains('SKU-0002', $skus);
    }
}
