<?php
/**
 * Tabel baris item order (PO & SO). Baris ditambah/dihapus oleh public/js/app.js.
 *
 * @var list<array<string, string>> $lines
 * @var array<string, string> $errors key "items.{n}.{field}" dan "items"
 * @var list<App\Entity\Product> $products
 * @var string|null $priceField nama input harga per baris (PO: "buy_price", SO: "price"); null = tanpa kolom harga (transfer)
 * @var string|null $priceLabel
 * @var 'buy'|'sell'|null $defaultPrice harga katalog yang diisikan otomatis saat produk dipilih
 * @var bool|null $showStock tampilkan petunjuk stok gudang asal via API (SO)
 * @var int $maxLines
 */
$showStock ??= false;
$priceLabel ??= 'Harga';
$defaultPrice ??= 'buy';
$lineError = static fn (int $i, string $field): ?string => $errors['items.' . $i . '.' . $field] ?? null;
$priceOf = static fn (App\Entity\Product $p): int => $defaultPrice === 'sell' ? $p->sellPrice : $p->buyPrice;
?>
<fieldset class="line-items">
  <legend>Item <span class="req">*</span></legend>
  <?php if (isset($errors['items'])): ?>
    <p class="error-msg" role="alert"><?= e($errors['items']) ?></p>
  <?php endif; ?>
  <div class="table-wrap line-items-scroll">
    <table class="data-table" data-line-items data-max-lines="<?= e($maxLines) ?>">
      <thead><tr><th>Produk</th><th class="num">Qty</th><?php if ($priceField !== null): ?><th class="num"><?= e($priceLabel) ?></th><?php endif; ?><th><span class="sr-only">Hapus</span></th></tr></thead>
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
              <input type="number" id="item-<?= e($i) ?>-qty" name="items[<?= e($i) ?>][qty]" value="<?= e($line['qty'] ?? '') ?>" min="1" step="1" inputmode="numeric" data-line-qty<?= $lineError($i, 'qty') !== null ? ' aria-invalid="true"' : '' ?>>
              <?php if ($lineError($i, 'qty') !== null): ?><span class="error-msg"><?= e($lineError($i, 'qty')) ?></span><?php endif; ?>
              <?php if ($showStock): ?><span class="line-stock" data-line-stock aria-live="polite"></span><?php endif; ?>
            </td>
            <?php if ($priceField !== null): ?>
            <td class="num">
              <label class="sr-only" for="item-<?= e($i) ?>-price"><?= e($priceLabel) ?> baris <?= e($i + 1) ?></label>
              <input type="number" id="item-<?= e($i) ?>-price" name="items[<?= e($i) ?>][<?= e($priceField) ?>]" value="<?= e($line[$priceField] ?? '') ?>" min="0" step="1" inputmode="numeric" data-line-price<?= $lineError($i, $priceField) !== null ? ' aria-invalid="true"' : '' ?>>
              <?php if ($lineError($i, $priceField) !== null): ?><span class="error-msg"><?= e($lineError($i, $priceField)) ?></span><?php endif; ?>
            </td>
            <?php endif; ?>
            <td><button type="button" class="btn small ghost" data-remove-line aria-label="Hapus baris <?= e($i + 1) ?>">✕</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="btn small" data-add-line>+ Tambah item</button>
  <p class="hint">
    Baris yang dibiarkan kosong akan diabaikan.
    <?php if ($priceField !== null): ?>Harga terisi otomatis dari harga <?= $defaultPrice === 'sell' ? 'jual' : 'beli' ?> katalog saat produk dipilih dan dapat diubah.<?php endif; ?>
  </p>
</fieldset>
