<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var array<string, string> $old
 * @var list<array<string, string>> $lines
 * @var array<string, string> $errors
 * @var array{suppliers: list<App\Entity\Party>, warehouses: list<App\Entity\Warehouse>, products: list<App\Entity\Product>} $options
 */

use App\Service\OrderLineValidator;

$common = ['old' => $old, 'errors' => $errors];
$supplierOptions = ['' => '— Pilih supplier —'];
foreach ($options['suppliers'] as $supplier) {
    $supplierOptions[(string) $supplier->id] = $supplier->name;
}
$warehouseOptions = ['' => '— Pilih gudang tujuan —'];
foreach ($options['warehouses'] as $warehouse) {
    $warehouseOptions[(string) $warehouse->id] = $warehouse->name;
}
$lineError = static fn (int $i, string $field): ?string => $errors['items.' . $i . '.' . $field] ?? null;
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Buat Purchase Order',
    'subtitle' => 'PO disimpan sebagai Draft; Admin menandainya Ordered setelah dikirim ke supplier',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form wide animate-in" method="post" action="/purchase-orders">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'required' => true, 'options' => $supplierOptions, 'hint' => 'Hanya supplier aktif.']) ?>
    <?= $view->partial('partials/form-field', $common + ['name' => 'warehouse_id', 'label' => 'Gudang Tujuan', 'type' => 'select', 'required' => true, 'options' => $warehouseOptions]) ?>
  </div>
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'order_date', 'label' => 'Tanggal Order', 'type' => 'date', 'required' => true, 'hint' => 'Tidak boleh di masa depan.']) ?>
  </div>

  <fieldset class="line-items">
    <legend>Item <span class="req">*</span></legend>
    <?php if (isset($errors['items'])): ?>
      <p class="error-msg" role="alert"><?= e($errors['items']) ?></p>
    <?php endif; ?>
    <div class="table-wrap line-items-scroll">
      <table class="data-table" data-line-items data-max-lines="<?= e(OrderLineValidator::MAX_LINES) ?>">
        <thead><tr><th>Produk</th><th class="num">Qty</th><th class="num">Harga Beli (Rp)</th><th><span class="sr-only">Hapus</span></th></tr></thead>
        <tbody>
          <?php foreach ($lines as $i => $line): ?>
            <tr data-line>
              <td>
                <label class="sr-only" for="item-<?= e($i) ?>-sku">Produk baris <?= e($i + 1) ?></label>
                <select id="item-<?= e($i) ?>-sku" name="items[<?= e($i) ?>][sku]" data-line-product<?= $lineError($i, 'sku') !== null ? ' aria-invalid="true"' : '' ?>>
                  <option value="">— Pilih produk —</option>
                  <?php foreach ($options['products'] as $product): ?>
                    <option value="<?= e($product->sku) ?>" data-price="<?= e($product->buyPrice) ?>"<?= ($line['sku'] ?? '') === $product->sku ? ' selected' : '' ?>><?= e($product->sku . ' — ' . $product->name) ?></option>
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
                <label class="sr-only" for="item-<?= e($i) ?>-price">Harga beli baris <?= e($i + 1) ?></label>
                <input type="number" id="item-<?= e($i) ?>-price" name="items[<?= e($i) ?>][buy_price]" value="<?= e($line['buy_price'] ?? '') ?>" min="0" step="1" inputmode="numeric" data-line-price<?= $lineError($i, 'buy_price') !== null ? ' aria-invalid="true"' : '' ?>>
                <?php if ($lineError($i, 'buy_price') !== null): ?><span class="error-msg"><?= e($lineError($i, 'buy_price')) ?></span><?php endif; ?>
              </td>
              <td><button type="button" class="btn small ghost" data-remove-line aria-label="Hapus baris <?= e($i + 1) ?>">✕</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="button" class="btn small" data-add-line>+ Tambah item</button>
    <p class="hint">Baris yang dibiarkan kosong akan diabaikan. Harga beli terisi otomatis dari data produk dan dapat diubah.</p>
  </fieldset>

  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan sebagai Draft</button>
    <a class="btn ghost" href="/purchase-orders">Batal</a>
  </div>
</form>
