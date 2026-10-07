<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewOrderLine;
use App\Repository\ProductRepositoryInterface;

/**
 * Validasi baris item, dipakai bersama oleh Purchase Order, Sales Order, dan
 * transfer stok (refactor R-02). Error ditulis ke validator form dengan key
 * "items.{n}.{field}" dan "items".
 */
final class OrderLineValidator
{
    public const MAX_LINES = 50;
    private const MAX_QTY = 100_000;
    private const MAX_PRICE = 999_999_999_999;

    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    /**
     * @param list<array<string, string>> $lines baris mentah: sku, qty, (opsional) field harga
     * @param string|null $priceField nama field harga dari input (PO: "buy_price", SO: "price");
     *                                null = baris tanpa harga (transfer stok), price diisi 0
     * @return list<NewOrderLine>
     */
    public function validate(InputValidator $validator, array $lines, ?string $priceField, string $priceLabel = 'Harga'): array
    {
        $result = [];
        $seen = [];
        foreach ($lines as $line) {
            $sku = strtoupper(trim($line['sku'] ?? ''));
            $lineInput = ['qty' => $line['qty'] ?? ''];
            if ($priceField !== null) {
                $lineInput[$priceField] = $line[$priceField] ?? '';
            }
            if ($sku === '' && implode('', array_map('trim', $lineInput)) === '') {
                continue; // baris kosong dari form diabaikan
            }
            $prefix = 'items.' . count($result) . '.';

            $this->checkProduct($validator, $prefix, $sku, isset($seen[$sku]));
            $seen[$sku] = true;

            $result[] = $this->parseLine($validator, $prefix, $sku, $lineInput, $priceField, $priceLabel);
        }

        if ($result === []) {
            $validator->addError('items', 'Tambahkan minimal satu item.');
        } elseif (count($result) > self::MAX_LINES) {
            $validator->addError('items', sprintf('Maksimal %d item per order.', self::MAX_LINES));
        }

        return $result;
    }

    private function checkProduct(InputValidator $validator, string $prefix, string $sku, bool $duplicate): void
    {
        $product = $this->products->findBySku($sku);
        if ($product === null || !$product->active) {
            $validator->addError($prefix . 'sku', 'Pilih produk yang aktif.');
        } elseif ($duplicate) {
            $validator->addError($prefix . 'sku', 'Produk ini sudah ada di baris lain; gabungkan qty-nya.');
        }
    }

    /**
     * Qty & harga satu baris; error ditulis ke validator form dengan prefix baris.
     *
     * @param array<string, string> $lineInput
     */
    private function parseLine(
        InputValidator $validator,
        string $prefix,
        string $sku,
        array $lineInput,
        ?string $priceField,
        string $priceLabel,
    ): NewOrderLine {
        $lineValidator = new InputValidator($lineInput);
        $qty = $lineValidator->wholeNumber('qty', 'Qty', self::MAX_QTY);
        if (!$lineValidator->hasError('qty') && $qty === 0) {
            $lineValidator->addError('qty', 'Qty minimal 1.');
        }
        $price = $priceField === null
            ? 0
            : $lineValidator->wholeNumber($priceField, $priceLabel, self::MAX_PRICE);
        foreach ($lineValidator->errors() as $field => $message) {
            $validator->addError($prefix . $field, $message);
        }

        return new NewOrderLine($sku, $qty, $price);
    }
}
