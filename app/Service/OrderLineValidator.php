<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewOrderLine;
use App\Repository\ProductRepositoryInterface;

/**
 * Validasi baris item order, dipakai bersama oleh Purchase Order dan Sales
 * Order (refactor R-02). Error ditulis ke validator form dengan key
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
     * @param string|null $priceField nama field harga dari input (PO: "buy_price"); null =
     *                                harga diambil dari harga jual produk di katalog (SO), tidak
     *                                bisa diubah pengguna
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

            $product = $this->products->findBySku($sku);
            if ($product === null || !$product->active) {
                $validator->addError($prefix . 'sku', 'Pilih produk yang aktif.');
            } elseif (isset($seen[$sku])) {
                $validator->addError($prefix . 'sku', 'Produk ini sudah ada di baris lain; gabungkan qty-nya.');
            }
            $seen[$sku] = true;

            $lineValidator = new InputValidator($lineInput);
            $qty = $lineValidator->wholeNumber('qty', 'Qty', self::MAX_QTY);
            if (!$lineValidator->hasError('qty') && $qty === 0) {
                $lineValidator->addError('qty', 'Qty minimal 1.');
            }
            $price = $priceField === null
                ? (int) $product?->sellPrice
                : $lineValidator->wholeNumber($priceField, $priceLabel, self::MAX_PRICE);
            foreach ($lineValidator->errors() as $field => $message) {
                $validator->addError($prefix . $field, $message);
            }

            $result[] = new NewOrderLine($sku, $qty, $price);
        }

        if ($result === []) {
            $validator->addError('items', 'Tambahkan minimal satu item.');
        } elseif (count($result) > self::MAX_LINES) {
            $validator->addError('items', sprintf('Maksimal %d item per order.', self::MAX_LINES));
        }

        return $result;
    }
}
