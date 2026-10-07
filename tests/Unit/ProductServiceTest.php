<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\UploadedFile;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Role;
use App\Entity\User;
use App\Service\BusinessRuleException;
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
    private User $admin;

    protected function setUp(): void
    {
        $this->admin = new User(7, 'Budi Admin', 'admin@ioms.test', 'x', Role::Admin, true);
        $this->products = new InMemoryProductRepository([1, 2]);
        $categories = new InMemoryCategoryRepository();
        $categories->add(new Category(1, 'Elektronik', null));
        $this->images = new FakeImageStorage();
        $this->service = new ProductService($this->products, $categories, $this->images);

        $this->products->seed(new Product('SKU-0001', 'Kabel HDMI', 1, 'pcs', 30000, 45000, 10, null, true), [1 => 3, 2 => 1]);
    }

    public function testCreateNormalisesSkuAndCreatesZeroStockInEveryWarehouse(): void
    {
        $product = $this->service->create($this->validInput(['sku' => ' new-01 ']), null, $this->admin);

        self::assertSame('NEW-01', $product->sku);
        self::assertSame(45000, $product->sellPrice);
        self::assertTrue($product->active);
        self::assertSame([0, 0], array_map(static fn ($level) => $level->quantity, $this->service->stockLevels('NEW-01')));
    }

    public function testCreateCollectsAllErrorsAtOnceAndSavesNothing(): void
    {
        try {
            $this->service->create(['sku' => 'SKU-0001', 'name' => '', 'category_id' => '99', 'unit' => '',
                'buy_price' => '', 'sell_price' => '-1', 'reorder_point' => '2.5', 'active' => 'yes'], null, $this->admin);
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

        $this->service->create($this->validInput([$field => $value]), null, $this->admin);
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
        $product = $this->service->create($this->validInput(['buy_price' => '0', 'sell_price' => '0', 'reorder_point' => '0']), null, $this->admin);

        self::assertSame(0, $product->reorderPoint);
    }

    #[DataProvider('invalidSkus')]
    public function testSkuFormatIsValidated(string $sku): void
    {
        try {
            $this->service->create($this->validInput(['sku' => $sku]), null, $this->admin);
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
            $this->service->create($this->validInput(), $image, $this->admin);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame(['image'], array_keys($e->errors));
        }

        self::assertSame([], $this->images->stored);
        self::assertFalse($this->products->skuExists('NEW-01'));
    }

    public function testValidImageIsStoredAndLinkedToProduct(): void
    {
        $product = $this->service->create($this->validInput(), new UploadedFile('/tmp/x', 'foto.png', 100, UPLOAD_ERR_OK), $this->admin);

        self::assertSame('/uploads/products/fake-0.png', $product->imageUrl);
    }

    public function testUpdateKeepsSkuAndExistingImageAndCanDeactivate(): void
    {
        $this->products->seed(new Product('SKU-0002', 'Mouse', 1, 'pcs', 80000, 120000, 15, '/uploads/products/lama.png', true), [1 => 5]);

        $updated = $this->edit('SKU-0002', ['sku' => 'GANTI-SKU', 'name' => 'Mouse Wireless', 'active' => '0']);

        self::assertSame('SKU-0002', $updated->sku);
        self::assertSame('/uploads/products/lama.png', $updated->imageUrl);
        self::assertFalse($updated->active);
        self::assertFalse($this->products->skuExists('GANTI-SKU'));
    }

    public function testUpdateDoesNotRequireUniqueSkuCheckAgainstItself(): void
    {
        $updated = $this->edit('SKU-0001', ['reorder_point' => '20']);

        self::assertSame(20, $updated->reorderPoint);
    }

    public function testCreateRecordsInitialPriceByActor(): void
    {
        $this->service->create($this->validInput(), null, $this->admin);

        $history = $this->service->priceHistory('NEW-01', includeBuyPrice: true);
        self::assertCount(1, $history);
        self::assertTrue($history[0]->isInitial());
        self::assertSame([30000, 45000], [$history[0]->newBuyPrice, $history[0]->newSellPrice]);
        self::assertSame(7, $this->products->priceChanges[0]->changedBy);
    }

    public function testUpdateRecordsOldAndNewPriceOnlyWhenAPriceChanges(): void
    {
        $this->edit('SKU-0001', ['name' => 'Kabel HDMI 2m']);
        self::assertCount(0, $this->service->priceHistory('SKU-0001', includeBuyPrice: true), 'harga sama: tidak ada riwayat');

        $this->edit('SKU-0001', ['sell_price' => '50000']);
        $history = $this->service->priceHistory('SKU-0001', includeBuyPrice: true);
        self::assertCount(1, $history);
        self::assertSame([30000, 30000, 45000, 50000], [$history[0]->oldBuyPrice, $history[0]->newBuyPrice, $history[0]->oldSellPrice, $history[0]->newSellPrice]);
    }

    public function testFailedValidationRecordsNoPriceChange(): void
    {
        try {
            $this->edit('SKU-0001', ['sell_price' => '99000', 'name' => '']);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException) {
        }

        self::assertSame([], $this->products->priceChanges);
    }

    public function testSalesDoesNotSeeRowsThatOnlyChangeBuyPrice(): void
    {
        $this->edit('SKU-0001', ['buy_price' => '32000']);
        $this->edit('SKU-0001', ['buy_price' => '32000', 'sell_price' => '48000']);

        self::assertCount(2, $this->service->priceHistory('SKU-0001', includeBuyPrice: true));
        $forSales = $this->service->priceHistory('SKU-0001', includeBuyPrice: false);
        self::assertCount(1, $forSales);
        self::assertSame(48000, $forSales[0]->newSellPrice);
    }

    public function testStaleFormIsRejectedBeforeAnythingIsSavedOrStored(): void
    {
        // Admin A dan B membuka form pada versi yang sama; A menyimpan lebih dulu.
        $openedByB = $this->products->findBySku('SKU-0001');
        self::assertNotNull($openedByB);
        $this->edit('SKU-0001', ['sell_price' => '50000']);
        $current = $this->products->findBySku('SKU-0001');
        self::assertNotNull($current);
        $writesBefore = $this->products->writes;

        try {
            $this->service->update(
                $current,
                $this->validInput(['sell_price' => '60000', 'version' => (string) $openedByB->updatedAt]),
                new UploadedFile('/tmp/x', 'foto.png', 100, UPLOAD_ERR_OK),
                $this->admin,
            );
            self::fail('BusinessRuleException seharusnya dilempar.');
        } catch (BusinessRuleException $e) {
            self::assertStringContainsString('sudah diubah pengguna lain', $e->getMessage());
        }

        self::assertSame($writesBefore, $this->products->writes);
        self::assertSame(50000, $this->products->findBySku('SKU-0001')?->sellPrice, 'perubahan A tidak tertimpa');
        self::assertSame([], $this->images->stored, 'gambar tidak disimpan untuk form usang');
    }

    public function testRepositoryRejectsStaleVersionEvenWhenServiceCheckPasses(): void
    {
        // Celah antara pembacaan $existing dan penyimpanan: keduanya usang namun
        // saling cocok, sehingga hanya pengecekan di repository yang menangkapnya.
        $stale = $this->products->findBySku('SKU-0001');
        self::assertNotNull($stale);
        $this->edit('SKU-0001', ['sell_price' => '50000']);

        $this->expectException(BusinessRuleException::class);
        $this->service->update($stale, $this->validInput(['sell_price' => '60000', 'version' => (string) $stale->updatedAt]), null, $this->admin);
    }

    /**
     * Alur form edit: muat produk terbaru, kirim versinya bersama input.
     *
     * @param array<string, string> $overrides
     */
    private function edit(string $sku, array $overrides): Product
    {
        $existing = $this->products->findBySku($sku);
        self::assertNotNull($existing);

        return $this->service->update($existing, $this->validInput($overrides + ['version' => (string) $existing->updatedAt]), null, $this->admin);
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
