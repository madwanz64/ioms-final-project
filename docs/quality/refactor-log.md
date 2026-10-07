# Refactoring Log (DESIGN-03)

Setiap entri: smell yang ditemukan, teknik refactoring, cuplikan sebelum/sesudah,
dan bukti bahwa perilaku tidak berubah.

---

## R-01 · Extract Class `InputValidator` dari `ProductService` (2026-10-07)

**Smell:**
- **Duplicate Code (calon).** Lima form master data berikutnya (kategori, gudang,
  supplier, customer, user) butuh aturan yang sama dengan form produk: teks wajib,
  panjang maksimal, angka bulat ≥ 0, dan status aktif `"1"/"0"`. Kalau tidak
  diekstrak, logic yang sama akan tersalin ke lima service.
- **Long Method.** `validateFields()` di `ProductService` sepanjang 40 baris dan
  mencampur aturan tiap field dengan cara mengumpulkan error.
- **Primitive Obsession / parsing ganda.** Input mentah divalidasi di
  `validateFields()`, lalu di-parse ulang dari string di `buildProduct()`
  (`(int) $input['buy_price']`, `trim($input['name'])`). Aturan "bagaimana
  membaca field ini" ditulis di dua tempat.

**Teknik:** Extract Class (`App\Service\InputValidator`), lalu Extract Method
(`validateNewSku`, `validatedProduct`). Pemanggilan `validateFields()`,
`validateWholeNumber()`, `validateImage()`, dan `buildProduct()` diganti satu
alur di `validatedProduct()`.

**Sebelum** (`ProductService`, 221 baris):

```php
public function create(array $input, ?UploadedFile $image): Product
{
    $sku = strtoupper(trim($input['sku'] ?? ''));
    $errors = $this->validateSku($sku) + $this->validateFields($input) + $this->validateImage($image);
    if ($errors !== []) {
        throw new ValidationException($errors);
    }
    $product = $this->buildProduct($sku, $input, $image === null ? null : $this->images->store($image));
    ...
}

private function validateFields(array $input): array
{
    $errors = [];
    $name = trim($input['name'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Nama produk wajib diisi.';
    } elseif (mb_strlen($name) > 150) {
        $errors['name'] = 'Nama produk maksimal 150 karakter.';
    }
    // ... pola yang sama untuk unit, 3 angka (loop + validateWholeNumber), status
    return $errors;
}

private function buildProduct(string $sku, array $input, ?string $imageUrl): Product
{
    return new Product($sku, trim($input['name']), (int) $input['category_id'], trim($input['unit']),
        (int) $input['buy_price'], (int) $input['sell_price'], (int) $input['reorder_point'],
        $imageUrl, $input['active'] === '1');
}
```

**Sesudah** (`ProductService` 146 baris + `InputValidator` 124 baris yang dipakai ulang):

```php
public function create(array $input, ?UploadedFile $image): Product
{
    $validator = new InputValidator($input);
    $sku = $this->validateNewSku($validator);
    $product = $this->validatedProduct($validator, $sku, $image, null);
    $this->products->create($product);

    return $product;
}

private function validatedProduct(InputValidator $validator, string $sku, ?UploadedFile $image, ?string $currentImageUrl): Product
{
    $name = $validator->requiredText('name', 'Nama produk', 150);
    // ...
    $buyPrice = $validator->wholeNumber('buy_price', 'Harga beli', self::MAX_PRICE);
    $active = $validator->activeFlag();
    $validator->throwIfInvalid();
    // nilai yang sudah dinormalisasi langsung dipakai, tanpa parsing ulang
    return new Product($sku, $name, (int) $categoryId, $unit, $buyPrice, $sellPrice, $reorderPoint, $imageUrl, $active);
}
```

**Bukti perilaku tidak berubah:** 42 test (termasuk 22 kasus validasi produk di
`ProductServiceTest`) lulus sebelum dan sesudah refactor **tanpa satu pun test
diubah**. Pesan error identik. PHPStan level 6 tetap 0 error.

**Commit:** `refactor: ekstrak InputValidator dari ProductService`.

---

## R-02 · Extract Class `OrderLineValidator` + Move Method tanggal ke `InputValidator` (2026-10-07)

**Smell:**
- **Duplicate Code (calon).** Sales Order butuh validasi baris item dan tanggal order
  yang persis sama dengan Purchase Order. Bedanya hanya sumber harga: PO dari input
  `buy_price`, SO dari harga jual katalog. Tanpa ekstraksi, 50 baris akan tersalin.
- **Large Class.** `PurchaseOrderService` (291 baris) mencampur aturan alur PO dengan
  detail parsing baris form.
- **Exceptions as control flow.** Error validator baris disalin ke validator form
  lewat `try { throwIfInvalid() } catch (ValidationException $e) { foreach ... }`.

**Teknik:** Extract Class (`OrderLineValidator`), Move Method (`validateOrderDate` →
`InputValidator::dateNotAfter`), Parameterize Method (`$priceField`: nama field harga,
atau `null` = harga katalog), dan Replace Exception with Test (`InputValidator::errors()`
menggantikan try/catch).

**Sebelum** (`PurchaseOrderService`):

```php
private function validateLines(InputValidator $validator, array $lines): array
{
    // ... 47 baris: lewati baris kosong, cek produk aktif & duplikat, qty >= 1,
    $price = $lineValidator->wholeNumber('buy_price', 'Harga beli', self::MAX_PRICE);
    try {
        $lineValidator->throwIfInvalid();
    } catch (ValidationException $e) {
        foreach ($e->errors as $field => $message) {
            $validator->addError($prefix . $field, $message);
        }
    }
    // ...
}
```

**Sesudah:**

```php
// PurchaseOrderService::create()
$orderDate = $validator->dateNotAfter('order_date', 'Tanggal order', $this->today);
$orderLines = $this->lineValidator->validate($validator, $lines, 'buy_price', 'Harga beli');

// SalesOrderService::create() — harga tidak bisa diisi pengguna
$orderLines = $this->lineValidator->validate($validator, $lines, null);

// OrderLineValidator
foreach ($lineValidator->errors() as $field => $message) {
    $validator->addError($prefix . $field, $message);
}
```

**Bukti perilaku tidak berubah:** 89 test lulus sebelum & sesudah tanpa test diubah,
termasuk 4 kasus tanggal (`2026-02-30`, masa depan, format lain, kosong) dan aturan item
di `PurchaseOrderServiceTest`. Signature konstruktor `PurchaseOrderService` sengaja
dipertahankan. Smoke test form PO: halaman 200, tanggal `2026-02-30` tetap ditolak.

**Commit:** `refactor: ekstrak OrderLineValidator & validasi tanggal untuk dipakai PO dan SO`.
