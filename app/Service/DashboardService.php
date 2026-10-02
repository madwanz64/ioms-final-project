<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductSummary;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductSearchCriteria;

/**
 * Ringkasan dashboard untuk slice pertama: hanya angka produk/stok, semuanya
 * dari query (DASH-01). Ringkasan order per status menyusul bersama modul PO/SO.
 */
final class DashboardService
{
    private const LOW_STOCK_LIMIT = 8;

    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    /**
     * @return array{activeProducts: int, lowStockCount: int, lowStock: list<ProductSummary>}
     */
    public function stockSummary(): array
    {
        $active = $this->products->search((new ProductSearchCriteria(perPage: 1))->withOnlyActive());
        $low = $this->products->search(
            (new ProductSearchCriteria(stockStatus: 'low', sort: 'stock_asc', perPage: self::LOW_STOCK_LIMIT))->withOnlyActive()
        );

        return [
            'activeProducts' => $active->total,
            'lowStockCount' => $low->total,
            'lowStock' => $low->items,
        ];
    }
}
