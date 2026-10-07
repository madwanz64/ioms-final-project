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
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Buat Purchase Order',
    'subtitle' => 'PO disimpan sebagai Draft, lalu ditandai Ordered setelah dikirim ke supplier',
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

  <?= $view->partial('partials/order-lines', [
      'lines' => $lines,
      'errors' => $errors,
      'products' => $options['products'],
      'priceField' => 'buy_price',
      'priceLabel' => 'Harga Beli (Rp)',
      'defaultPrice' => 'buy',
      'maxLines' => OrderLineValidator::MAX_LINES,
  ]) ?>

  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan sebagai Draft</button>
    <a class="btn ghost" href="/purchase-orders">Batal</a>
  </div>
</form>
