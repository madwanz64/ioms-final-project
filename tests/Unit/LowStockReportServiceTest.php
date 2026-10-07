<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\LowStockLine;
use App\Entity\Product;
use App\Service\LowStockReportService;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryProductRepository;

/**
 * Area logic: ringkasan low stock untuk script terjadwal (JOB-01).
 */
final class LowStockReportServiceTest extends TestCase
{
    public function testListsOnlyActiveLowStockProductsWithLargestShortageFirst(): void
    {
        $repo = new InMemoryProductRepository();
        $repo->seed($this->product('A-01', reorder: 10), [1 => 3, 2 => 1]);   // kurang 6
        $repo->seed($this->product('A-02', reorder: 15), [1 => 1, 2 => 1]);   // kurang 13
        $repo->seed($this->product('A-03', reorder: 5), [1 => 5, 2 => 0]);    // tepat 5 -> bukan low
        $repo->seed($this->product('A-04', reorder: 50, active: false), [1 => 0]); // nonaktif -> diabaikan

        $lines = (new LowStockReportService($repo))->lines();

        self::assertSame(['A-02', 'A-01'], array_map(static fn (LowStockLine $l): string => $l->sku, $lines));
        self::assertSame(13, $lines[0]->shortage());
        self::assertSame([3, 1], array_map(static fn ($level): int => $level->quantity, $lines[1]->levels), 'rincian per gudang ikut');
    }

    public function testEmptyWhenNothingIsBelowReorderPoint(): void
    {
        $repo = new InMemoryProductRepository();
        $repo->seed($this->product('A-01', reorder: 2), [1 => 10]);

        self::assertSame([], (new LowStockReportService($repo))->lines());
    }

    public function testReadsEveryPageNotOnlyTheFirstHundred(): void
    {
        $repo = new InMemoryProductRepository();
        for ($i = 1; $i <= 130; $i++) {
            $repo->seed($this->product(sprintf('P-%03d', $i), reorder: 10), [1 => 0]);
        }

        self::assertCount(130, (new LowStockReportService($repo))->lines());
    }

    private function product(string $sku, int $reorder, bool $active = true): Product
    {
        return new Product($sku, 'Produk ' . $sku, 1, 'pcs', 1000, 2000, $reorder, null, $active);
    }
}
