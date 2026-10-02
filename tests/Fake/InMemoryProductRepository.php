<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Entity\StockLevel;
use App\Repository\PaginatedResult;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductSearchCriteria;

/**
 * Implementasi kedua ProductRepositoryInterface (ARCH-01) untuk unit test:
 * menyimpan data di array, tanpa PDO. Perilaku search meniru versi MySQL
 * secukupnya (filter, sort, pagination) agar service bisa diuji apa adanya.
 */
final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, array<int, int>> sku => [warehouseId => quantity] */
    private array $stock = [];

    /** @var list<int> */
    private array $warehouseIds;

    /** Jumlah pemanggilan create() + update(), untuk memastikan data gagal validasi tidak tersimpan. */
    public int $writes = 0;

    /**
     * @param list<int> $warehouseIds
     */
    public function __construct(array $warehouseIds = [1, 2])
    {
        $this->warehouseIds = $warehouseIds;
    }

    /**
     * @param array<int, int> $stockPerWarehouse
     */
    public function seed(Product $product, array $stockPerWarehouse = []): void
    {
        $this->products[$product->sku] = $product;
        $this->stock[$product->sku] = $stockPerWarehouse;
    }

    public function search(ProductSearchCriteria $criteria): PaginatedResult
    {
        $rows = [];
        foreach ($this->products as $product) {
            $summary = new ProductSummary(
                $product->sku,
                $product->name,
                'Kategori ' . $product->categoryId,
                $product->sellPrice,
                $product->reorderPoint,
                array_sum($this->stock[$product->sku] ?? []),
                $product->active,
            );
            if ($this->matches($summary, $product, $criteria)) {
                $rows[] = $summary;
            }
        }

        usort($rows, static fn (ProductSummary $a, ProductSummary $b): int => match ($criteria->sort) {
            'name_desc' => strcmp($b->name, $a->name),
            'stock_asc' => $a->totalStock <=> $b->totalStock,
            default => strcmp($a->name, $b->name),
        });

        $total = count($rows);
        $page = min($criteria->page, max(1, (int) ceil($total / $criteria->perPage)));

        return new PaginatedResult(array_slice($rows, ($page - 1) * $criteria->perPage, $criteria->perPage), $total, $page, $criteria->perPage);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->products[$sku] ?? null;
    }

    public function skuExists(string $sku): bool
    {
        return isset($this->products[$sku]);
    }

    public function create(Product $product): void
    {
        $this->writes++;
        $this->products[$product->sku] = $product;
        $this->stock[$product->sku] = array_fill_keys($this->warehouseIds, 0);
    }

    public function update(Product $product): void
    {
        $this->writes++;
        $this->products[$product->sku] = $product;
    }

    public function stockLevels(string $sku): array
    {
        $levels = [];
        foreach ($this->stock[$sku] ?? [] as $warehouseId => $quantity) {
            $levels[] = new StockLevel($warehouseId, 'Gudang ' . $warehouseId, $quantity);
        }

        return $levels;
    }

    public function recentMovements(string $sku, int $limit): array
    {
        return [];
    }

    private function matches(ProductSummary $summary, Product $product, ProductSearchCriteria $criteria): bool
    {
        if ($criteria->onlyActive && !$product->active) {
            return false;
        }
        if ($criteria->categoryId !== null && $product->categoryId !== $criteria->categoryId) {
            return false;
        }
        if ($criteria->search !== ''
            && stripos($product->name, $criteria->search) === false
            && stripos($product->sku, $criteria->search) === false) {
            return false;
        }

        return match ($criteria->stockStatus) {
            'low' => $summary->isLowStock(),
            'normal' => !$summary->isLowStock(),
            default => true,
        };
    }
}
