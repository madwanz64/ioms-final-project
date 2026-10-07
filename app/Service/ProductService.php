<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\UploadedFile;
use App\Entity\Category;
use App\Entity\PriceChange;
use App\Entity\PriceHistoryEntry;
use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Entity\StockLevel;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\PaginatedResult;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductSearchCriteria;

/**
 * Aturan bisnis katalog produk (PRD-01, WH-01, FIND-01).
 * Backend adalah sumber kebenaran validasi (VAL-01): validasi HTML5/JS di
 * form hanya kenyamanan, semua aturan diperiksa ulang di sini.
 */
final class ProductService
{
    public const SKU_PATTERN = '/^[A-Z0-9-]{3,20}$/';
    private const MAX_PRICE = 999_999_999_999; // batas DECIMAL(14,2)
    private const MAX_REORDER_POINT = 1_000_000;
    private const RECENT_MOVEMENTS = 10;
    private const RECENT_PRICE_CHANGES = 10;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ImageStorageInterface $images,
    ) {
    }

    /**
     * @return PaginatedResult<ProductSummary>
     */
    public function search(ProductSearchCriteria $criteria): PaginatedResult
    {
        return $this->products->search($criteria);
    }

    /**
     * @return list<Category>
     */
    public function categories(): array
    {
        return $this->categories->all();
    }

    public function find(string $sku): ?Product
    {
        return $this->products->findBySku($sku);
    }

    /**
     * @return list<StockLevel>
     */
    public function stockLevels(string $sku): array
    {
        return $this->products->stockLevels($sku);
    }

    /**
     * @return list<StockMovement>
     */
    public function recentMovements(string $sku): array
    {
        return $this->products->recentMovements($sku, self::RECENT_MOVEMENTS);
    }

    /**
     * Tanpa $includeBuyPrice (role Sales), baris yang hanya mengubah harga beli
     * dibuang: harga beli tidak boleh terlihat, jadi baris itu tidak bermakna.
     *
     * @return list<PriceHistoryEntry>
     */
    public function priceHistory(string $sku, bool $includeBuyPrice): array
    {
        $entries = $this->products->priceHistory($sku, self::RECENT_PRICE_CHANGES);
        if ($includeBuyPrice) {
            return $entries;
        }

        return array_values(array_filter($entries, static fn (PriceHistoryEntry $entry): bool => $entry->sellPriceChanged()));
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function create(array $input, ?UploadedFile $image, User $actor): Product
    {
        $validator = new InputValidator($input);
        $sku = $this->validateNewSku($validator);
        $product = $this->validatedProduct($validator, $sku, $image, null);
        $this->products->create($product, PriceChange::initial($product, $actor->id));

        return $product;
    }

    /**
     * SKU tidak bisa diubah: SKU adalah primary key yang direferensikan
     * riwayat order dan stock ledger. Perubahan harga beli/jual dicatat ke
     * riwayat harga beserta siapa yang mengubahnya.
     *
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function update(Product $existing, array $input, ?UploadedFile $image, User $actor): Product
    {
        $product = $this->validatedProduct(new InputValidator($input), $existing->sku, $image, $existing->imageUrl);
        $this->products->update($product, PriceChange::between($existing, $product, $actor->id));

        return $product;
    }

    private function validateNewSku(InputValidator $validator): string
    {
        $sku = strtoupper($validator->raw('sku'));
        if ($sku === '') {
            $validator->addError('sku', 'SKU wajib diisi.');
        } elseif (preg_match(self::SKU_PATTERN, $sku) !== 1) {
            $validator->addError('sku', 'SKU 3–20 karakter, hanya huruf, angka, dan tanda minus.');
        } elseif ($this->products->skuExists($sku)) {
            $validator->addError('sku', 'SKU sudah dipakai produk lain.');
        }

        return $sku;
    }

    /**
     * Validasi seluruh field (termasuk gambar) lalu bangun Product. Gambar
     * baru hanya disimpan setelah SEMUA validasi lolos, supaya tidak ada file
     * tertinggal untuk produk yang ditolak.
     *
     * @throws ValidationException
     */
    private function validatedProduct(InputValidator $validator, string $sku, ?UploadedFile $image, ?string $currentImageUrl): Product
    {
        $name = $validator->requiredText('name', 'Nama produk', 150);
        $categoryId = $validator->positiveId('category_id');
        if ($categoryId === null || $this->categories->findById($categoryId) === null) {
            $validator->addError('category_id', 'Pilih kategori yang valid.');
        }
        $unit = $validator->requiredText('unit', 'Unit', 20);
        $buyPrice = $validator->wholeNumber('buy_price', 'Harga beli', self::MAX_PRICE);
        $sellPrice = $validator->wholeNumber('sell_price', 'Harga jual', self::MAX_PRICE);
        $reorderPoint = $validator->wholeNumber('reorder_point', 'Reorder point', self::MAX_REORDER_POINT);
        $active = $validator->activeFlag();
        $imageError = $image === null ? null : $this->images->validate($image);
        if ($imageError !== null) {
            $validator->addError('image', $imageError);
        }
        $validator->throwIfInvalid();

        $imageUrl = $image === null ? $currentImageUrl : $this->images->store($image);

        return new Product($sku, $name, (int) $categoryId, $unit, $buyPrice, $sellPrice, $reorderPoint, $imageUrl, $active);
    }
}
