<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\UploadedFile;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductSummary;
use App\Entity\StockLevel;
use App\Entity\StockMovement;
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
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function create(array $input, ?UploadedFile $image): Product
    {
        $sku = strtoupper(trim($input['sku'] ?? ''));
        $errors = $this->validateSku($sku) + $this->validateFields($input) + $this->validateImage($image);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $product = $this->buildProduct($sku, $input, $image === null ? null : $this->images->store($image));
        $this->products->create($product);

        return $product;
    }

    /**
     * SKU tidak bisa diubah: SKU adalah primary key yang direferensikan
     * riwayat order dan stock ledger.
     *
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function update(Product $existing, array $input, ?UploadedFile $image): Product
    {
        $errors = $this->validateFields($input) + $this->validateImage($image);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $imageUrl = $image === null ? $existing->imageUrl : $this->images->store($image);
        $product = $this->buildProduct($existing->sku, $input, $imageUrl);
        $this->products->update($product);

        return $product;
    }

    /**
     * @return array<string, string>
     */
    private function validateSku(string $sku): array
    {
        if ($sku === '') {
            return ['sku' => 'SKU wajib diisi.'];
        }
        if (preg_match(self::SKU_PATTERN, $sku) !== 1) {
            return ['sku' => 'SKU 3–20 karakter, hanya huruf, angka, dan tanda minus.'];
        }
        if ($this->products->skuExists($sku)) {
            return ['sku' => 'SKU sudah dipakai produk lain.'];
        }

        return [];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validateFields(array $input): array
    {
        $errors = [];

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Nama produk wajib diisi.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Nama produk maksimal 150 karakter.';
        }

        $categoryId = $input['category_id'] ?? '';
        if (!ctype_digit($categoryId) || $this->categories->findById((int) $categoryId) === null) {
            $errors['category_id'] = 'Pilih kategori yang valid.';
        }

        $unit = trim($input['unit'] ?? '');
        if ($unit === '') {
            $errors['unit'] = 'Unit wajib diisi.';
        } elseif (mb_strlen($unit) > 20) {
            $errors['unit'] = 'Unit maksimal 20 karakter.';
        }

        $numbers = [
            'buy_price' => ['Harga beli', self::MAX_PRICE],
            'sell_price' => ['Harga jual', self::MAX_PRICE],
            'reorder_point' => ['Reorder point', self::MAX_REORDER_POINT],
        ];
        foreach ($numbers as $field => [$label, $max]) {
            $error = $this->validateWholeNumber($input[$field] ?? '', $label, $max);
            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        if (!in_array($input['active'] ?? '', ['0', '1'], true)) {
            $errors['active'] = 'Status tidak valid.';
        }

        return $errors;
    }

    private function validateWholeNumber(string $value, string $label, int $max): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return $label . ' wajib diisi.';
        }
        // ctype_digit menolak "-5", "1.5", "1e3", dan spasi — jadi hanya bilangan bulat >= 0 yang lolos.
        if (!ctype_digit($value)) {
            return $label . ' harus bilangan bulat >= 0.';
        }
        if (strlen(ltrim($value, '0')) > strlen((string) $max) || (int) $value > $max) {
            return sprintf('%s maksimal %s.', $label, number_format($max, 0, ',', '.'));
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function validateImage(?UploadedFile $image): array
    {
        $error = $image === null ? null : $this->images->validate($image);

        return $error === null ? [] : ['image' => $error];
    }

    /**
     * @param array<string, string> $input sudah lolos validasi
     */
    private function buildProduct(string $sku, array $input, ?string $imageUrl): Product
    {
        return new Product(
            $sku,
            trim($input['name']),
            (int) $input['category_id'],
            trim($input['unit']),
            (int) $input['buy_price'],
            (int) $input['sell_price'],
            (int) $input['reorder_point'],
            $imageUrl,
            $input['active'] === '1',
        );
    }
}
