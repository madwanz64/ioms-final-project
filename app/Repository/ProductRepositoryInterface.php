<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PriceChange;
use App\Entity\PriceHistoryEntry;
use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Entity\StockLevel;
use App\Entity\StockMovement;

/**
 * Boundary akses data produk (ARCH-01). Implementasi: MySqlProductRepository
 * (produksi & integration test) dan Tests\Fake\InMemoryProductRepository
 * (unit test tanpa database).
 *
 * Sengaja tidak ada method delete: produk hanya dinonaktifkan (§1.3).
 */
interface ProductRepositoryInterface
{
    /**
     * @return PaginatedResult<ProductSummary>
     */
    public function search(ProductSearchCriteria $criteria): PaginatedResult;

    public function findBySku(string $sku): ?Product;

    public function skuExists(string $sku): bool;

    /**
     * Semua produk aktif, urut nama (pilihan item pada form order).
     *
     * @return list<Product>
     */
    public function allActive(): array;

    /**
     * Simpan produk baru sekaligus membuat baris stok 0 di setiap gudang (WH-01)
     * dan mencatat harga awalnya, dalam satu transaksi.
     */
    public function create(Product $product, PriceChange $initialPrice): void;

    /**
     * Simpan perubahan produk; bila $priceChange tidak null, riwayat harganya
     * ditulis dalam transaksi yang sama.
     */
    public function update(Product $product, ?PriceChange $priceChange): void;

    /**
     * @return list<StockLevel>
     */
    public function stockLevels(string $sku): array;

    /**
     * @return list<StockMovement>
     */
    public function recentMovements(string $sku, int $limit): array;

    /**
     * Perubahan harga terbaru lebih dulu.
     *
     * @return list<PriceHistoryEntry>
     */
    public function priceHistory(string $sku, int $limit): array;
}
