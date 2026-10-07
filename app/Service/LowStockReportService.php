<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LowStockLine;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductSearchCriteria;

/**
 * Ringkasan produk di bawah reorder point untuk script terjadwal
 * (scripts/check-low-stock.php, JOB-01). Memakai filter "low" yang sama dengan
 * daftar produk & dashboard, jadi ketiganya selalu sepakat produk mana yang low stock.
 */
final class LowStockReportService
{
    private const PAGE_SIZE = 100;

    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    /**
     * Semua produk aktif yang low stock, kekurangan terbesar lebih dulu.
     *
     * @return list<LowStockLine>
     */
    public function lines(): array
    {
        $lines = [];
        $page = 1;
        do {
            $result = $this->products->search(
                (new ProductSearchCriteria(stockStatus: 'low', sort: 'stock_asc', page: $page, perPage: self::PAGE_SIZE))->withOnlyActive()
            );
            foreach ($result->items as $item) {
                $lines[] = new LowStockLine(
                    $item->sku,
                    $item->name,
                    $item->categoryName,
                    $item->totalStock,
                    $item->reorderPoint,
                    $this->products->stockLevels($item->sku),
                );
            }
            $page++;
        } while ($page <= $result->totalPages());

        usort($lines, static fn (LowStockLine $a, LowStockLine $b): int => [$b->shortage(), $a->sku] <=> [$a->shortage(), $b->sku]);

        return $lines;
    }
}
