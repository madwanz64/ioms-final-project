<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Service\InputValidator;
use App\Service\OrderLineValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryProductRepository;

/**
 * Aturan baris item bersama PO/SO/transfer (R-02) yang tidak tercakup test service.
 */
final class OrderLineValidatorTest extends TestCase
{
    private OrderLineValidator $lines;

    protected function setUp(): void
    {
        $products = new InMemoryProductRepository();
        $products->seed(new Product('SKU-0001', 'Kabel', 1, 'pcs', 1000, 2000, 5, null, true));
        $products->seed(new Product('SKU-0002', 'Lama', 1, 'pcs', 1000, 2000, 5, null, false));
        $this->lines = new OrderLineValidator($products);
    }

    public function testMoreThanMaxLinesIsRejected(): void
    {
        $validator = new InputValidator([]);
        $rows = array_fill(0, OrderLineValidator::MAX_LINES + 1, ['sku' => 'SKU-0001', 'qty' => '1']);

        $this->lines->validate($validator, $rows, null);

        self::assertSame(sprintf('Maksimal %d item per order.', OrderLineValidator::MAX_LINES), $validator->errors()['items']);
    }

    public function testInactiveDuplicateZeroQtyAndBadPriceAreReportedPerLine(): void
    {
        $validator = new InputValidator([]);
        $rows = [
            ['sku' => 'sku-0001', 'qty' => '2', 'price' => '2500'],
            ['sku' => 'SKU-0001', 'qty' => '0', 'price' => '2500'],
            ['sku' => 'SKU-0002', 'qty' => '1', 'price' => 'abc'],
            ['sku' => '', 'qty' => '', 'price' => ''],
        ];

        $result = $this->lines->validate($validator, $rows, 'price', 'Harga jual');

        self::assertCount(3, $result, 'Baris kosong diabaikan.');
        self::assertSame(['SKU-0001', 2, 2500], [$result[0]->sku, $result[0]->qty, $result[0]->price]);
        self::assertSame([
            'items.1.sku' => 'Produk ini sudah ada di baris lain; gabungkan qty-nya.',
            'items.1.qty' => 'Qty minimal 1.',
            'items.2.sku' => 'Pilih produk yang aktif.',
            'items.2.price' => 'Harga jual harus bilangan bulat >= 0.',
        ], $validator->errors());
    }
}
