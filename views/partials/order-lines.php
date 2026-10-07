<?php
/**
 * Tabel baris item order (PO & SO). Baris ditambah/dihapus oleh public/js/app.js.
 *
 * @var list<array<string, string>> $lines
 * @var array<string, string> $errors key "items.{n}.{field}" dan "items"
 * @var list<App\Entity\Product> $products
 * @var string|null $priceField nama input harga (PO: "buy_price"); null = harga jual katalog, read-only (SO)
 * @var string $priceLabel
 * @var int $maxLines
 */
$lineError = static fn (int $i, string $field): ?string => $errors['items.' . $i . '.' . $field] ?? null;
$priceOf = static fn (App\Entity\Product $p): int => $priceField === null ? $p->sellPrice : $p->buyPrice;
?>
<fieldset class="line-items">
  <legend>Item <span class="req">*</span></legend>
  <?php if (isset($errors['items'])): ?>
    <p class="error-msg" role="alert"><?= e($errors['items']) ?></p>
  <?php endif; ?>
  <div class="table-wrap line-items-scroll">
    <table class="data-table" data-line-items data-max-lines="<?= e($maxLines) ?>">
      <thead><tr><th>Produk</th><th class="num">Qty</th><th class="num"><?= e($priceLabel) ?></th><th><span class="sr-only">Hapus</span></th></tr></thead>
      <tbody>
        <?php foreach ($lines as $i => $line): ?>
          <tr data-line>
            <td>
              <label class="sr-only" for="item-<?= e($i) ?>-sku">Produk baris <?= e($i + 1) ?></label>
              <select id="item-<?= e($i) ?>-sku" name="items[<?= e($i) ?>][sku]" data-line-product<?= $lineError($i, 'sku') !== null ? ' aria-invalid="true"' : '' ?>>
                <option value="">— Pilih produk —</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= e($product->sku) ?>" data-price="<?= e($priceOf($product)) ?>"<?= ($line['sku'] ?? '') === $product->sku ? ' selected' : '' ?>><?= e($product->sku . ' — ' . $product->name) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($lineError($i, 'sku') !== null): ?><span class="error-msg"><?= e($lineError($i, 'sku')) ?></span><?php endif; ?>
            </td>
            <td class="num">
              <label class="sr-only" for="item-<?= e($i) ?>-qty">Qty baris <?= e($i + 1) ?></label>
              <input type="number" id="item-<?= e($i) ?>-qty" name="items[<?= e($i) ?>][qty]" value="<?= e($line['qty'] ?? '') ?>" min="1" step="1" inputmode="numeric"<?= $lineError($i, 'qty') !== null ? ' aria-invalid="true"' : '' ?>>
              <?php if ($lineError($i, 'qty') !== null): ?><span class="error-msg"><?= e($lineError($i, 'qty')) ?></span><?php endif; ?>
            </td>
            <td class="num">
              <?php if ($priceField === null): ?>
                <span data-line-price-text><?php foreach ($products as $product): ?><?= ($line['sku'] ?? '') === $product->sku ? e(rupiah($product->sellPrice)) : '' ?><?php endforeach; ?></span>
              <?php else: ?>
                <label class="sr-only" for="item-<?= e($i) ?>-price"><?= e($priceLabel) ?> baris <?= e($i + 1) ?></label>
                <input type="number" id="item-<?= e($i) ?>-price" name="items[<?= e($i) ?>][<?= e($priceField) ?>]" value="<?= e($line[$priceField] ?? '') ?>" min="0" step="1" inputmode="numeric" data-line-price<?= $lineError($i, $priceField) !== null ? ' aria-invalid="true"' : '' ?>>
                <?php if ($lineError($i, $priceField) !== null): ?><span class="error-msg"><?= e($lineError($i, $priceField)) ?></span><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><button type="button" class="btn small ghost" data-remove-line aria-label="Hapus baris <?= e($i + 1) ?>">✕</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="btn small" data-add-line>+ Tambah item</button>
  <p class="hint">
    Baris yang dibiarkan kosong akan diabaikan.
    <?= $priceField === null ? 'Harga mengikuti harga jual katalog dan tidak dapat diubah.' : 'Harga terisi otomatis dari data produk dan dapat diubah.' ?>
  </p>
</fieldset>
