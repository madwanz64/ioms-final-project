<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\UploadedFile;
use App\Entity\Category;
use App\Entity\Product;
use App\Service\ProductService;
use App\Service\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeImageStorage;
use Tests\Fake\InMemoryCategoryRepository;
use Tests\Fake\InMemoryProductRepository;

final class ProductServiceTest extends TestCase
{
    private InMemoryProductRepository $products;
    private FakeImageStorage $images;
    private ProductService $service;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository([1, 2]);
        $categories = new InMemoryCategoryRepository();
        $categories->add(new Category(1, 'Elektronik', null));
        $this->images = new FakeImageStorage();
        $this->service = new ProductService($this->products, $categories, $this->images);

        $this->products->seed(new Product('SKU-0001', 'Kabel HDMI', 1, 'pcs', 30000, 45000, 10, null, true), [1 => 3, 2 => 1]);
    }

    public function testCreateNormalisesSkuAndCreatesZeroStockInEveryWarehouse(): void
    {
        $product = $this->service->create($this->validInput(['sku' => ' new-01 ']), null);

        self::assertSame('NEW-01', $product->sku);
        self::assertSame(45000, $product->sellPrice);
        self::assertTrue($product->active);
        self::assertSame([0, 0], array_map(static fn ($level) => $level->quantity, $this->service->stockLevels('NEW-01')));
    }

    public function testCreateCollectsAllErrorsAtOnceAndSavesNothing(): void
    {
        try {
            $this->service->create(['sku' => 'SKU-0001', 'name' => '', 'category_id' => '99', 'unit' => '',
                'buy_price' => '', 'sell_price' => '-1', 'reorder_point' => '2.5', 'active' => 'yes'], null);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame(
                ['sku', 'name', 'category_id', 'unit', 'buy_price', 'sell_price', 'reorder_point', 'active'],
                array_keys($e->errors)
            );
            self::assertSame('SKU sudah dipakai produk lain.', $e->errors['sku']);
        }

        self::assertSame(0, $this->products->writes);
    }

    #[DataProvider('invalidNumbers')]
    public function testNumbersMustBeWholeAndNonNegative(string $field, string $value): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create($this->validInput([$field => $value]), null);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'reorder point negatif' => ['reorder_point', '-1'],
            'reorder point desimal' => ['reorder_point', '1.5'],
            'reorder point notasi ilmiah' => ['reorder_point', '1e3'],
            'reorder point melebihi batas' => ['reorder_point', '1000001'],
            'harga beli negatif' => ['buy_price', '-100'],
            'harga jual bukan angka' => ['sell_price', 'gratis'],
            'harga jual overflow' => ['sell_price', '99999999999999999999'],
        ];
    }

    public function testZeroIsAValidPriceAndReorderPoint(): void
    {
        $product = $this->service->create($this->validInput(['buy_price' => '0', 'sell_price' => '0', 'reorder_point' => '0']), null);

        self::assertSame(0, $product->reorderPoint);
    }

    #[DataProvider('invalidUnits')]
    public function testUnitMustStartWithALetter(string $unit): void
    {
        try {
            $this->service->create($this->validInput(['unit' => $unit]), null);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('unit', $e->errors);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUnits(): array
    {
        return ['angka negatif' => ['-55'], 'angka saja' => ['10'], 'simbol' => ['#pcs'], 'terlalu panjang' => [str_repeat('a', 21)]];
    }

    public function testCommonUnitsAreAccepted(): void
    {
        foreach (['pcs', 'box', 'rim', 'm2', 'box/12', 'Lusin'] as $unit) {
            self::assertSame($unit, $this->service->create($this->validInput(['sku' => 'NEW-' . strtoupper(bin2hex($unit)), 'unit' => $unit]), null)->unit);
        }
    }

    #[DataProvider('invalidSkus')]
    public function testSkuFormatIsValidated(string $sku): void
    {
        try {
            $this->service->create($this->validInput(['sku' => $sku]), null);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('sku', $e->errors);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSkus(): array
    {
        return ['kosong' => [''], 'terlalu pendek' => ['AB'], 'ada spasi' => ['SKU 01'], 'karakter ilegal' => ['SKU_01;'], 'terlalu panjang' => [str_repeat('A', 21)]];
    }

    public function testInvalidImageRejectsWholeProductAndStoresNoFile(): void
    {
        $image = new UploadedFile('/tmp/x', 'invalid.png', 100, UPLOAD_ERR_OK);

        try {
            $this->service->create($this->validInput(), $image);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame(['image'], array_keys($e->errors));
        }

        self::assertSame([], $this->images->stored);
        self::assertFalse($this->products->skuExists('NEW-01'));
    }

    public function testValidImageIsStoredAndLinkedToProduct(): void
    {
        $product = $this->service->create($this->validInput(), new UploadedFile('/tmp/x', 'foto.png', 100, UPLOAD_ERR_OK));

        self::assertSame('/uploads/products/fake-0.png', $product->imageUrl);
    }

    public function testUpdateKeepsSkuAndExistingImageAndCanDeactivate(): void
    {
        $existing = new Product('SKU-0002', 'Mouse', 1, 'pcs', 80000, 120000, 15, '/uploads/products/lama.png', true);
        $this->products->seed($existing, [1 => 5]);

        $updated = $this->service->update($existing, $this->validInput(['sku' => 'GANTI-SKU', 'name' => 'Mouse Wireless', 'active' => '0']), null);

        self::assertSame('SKU-0002', $updated->sku);
        self::assertSame('/uploads/products/lama.png', $updated->imageUrl);
        self::assertFalse($updated->active);
        self::assertFalse($this->products->skuExists('GANTI-SKU'));
    }

    public function testUpdateDoesNotRequireUniqueSkuCheckAgainstItself(): void
    {
        $existing = $this->products->findBySku('SKU-0001');
        self::assertNotNull($existing);

        $updated = $this->service->update($existing, $this->validInput(['reorder_point' => '20']), null);

        self::assertSame(20, $updated->reorderPoint);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return $overrides + [
            'sku' => 'NEW-01',
            'name' => 'Produk Baru',
            'category_id' => '1',
            'unit' => 'pcs',
            'buy_price' => '30000',
            'sell_price' => '45000',
            'reorder_point' => '5',
            'active' => '1',
        ];
    }
}
