<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\PriceChange;
use App\Entity\PriceHistoryEntry;
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

    /** @var list<PriceChange> urutan dicatat (lama → baru) */
    public array $priceChanges = [];

    /** Pengganti updated_at MySQL: setiap simpanan mendapat versi baru. */
    private int $version = 0;

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
        $this->products[$product->sku] = $this->stamped($product);
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

    public function allActive(): array
    {
        return array_values(array_filter($this->products, static fn (Product $product): bool => $product->active));
    }

    public function skuExists(string $sku): bool
    {
        return isset($this->products[$sku]);
    }

    public function create(Product $product, PriceChange $initialPrice): void
    {
        $this->writes++;
        $this->products[$product->sku] = $this->stamped($product);
        $this->stock[$product->sku] = array_fill_keys($this->warehouseIds, 0);
        $this->priceChanges[] = $initialPrice;
    }

    public function update(Product $product, string $expectedVersion, ?PriceChange $priceChange): bool
    {
        if (($this->products[$product->sku] ?? null)?->updatedAt !== $expectedVersion) {
            return false;
        }
        $this->writes++;
        $this->products[$product->sku] = $this->stamped($product);
        if ($priceChange !== null) {
            $this->priceChanges[] = $priceChange;
        }

        return true;
    }

    public function priceHistory(string $sku, int $limit): array
    {
        $entries = [];
        foreach (array_reverse($this->priceChanges) as $change) {
            if ($change->sku === $sku) {
                $entries[] = new PriceHistoryEntry(
                    '2026-01-01 00:00:00',
                    'User ' . $change->changedBy,
                    $change->oldBuyPrice,
                    $change->newBuyPrice,
                    $change->oldSellPrice,
                    $change->newSellPrice,
                );
            }
        }

        return array_slice($entries, 0, $limit);
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

    private function stamped(Product $p): Product
    {
        return new Product($p->sku, $p->name, $p->categoryId, $p->unit, $p->buyPrice, $p->sellPrice, $p->reorderPoint, $p->imageUrl, $p->active, 'v' . ++$this->version);
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
