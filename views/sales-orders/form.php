<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var array<string, string> $old
 * @var list<array<string, string>> $lines
 * @var array<string, string> $errors
 * @var array{customers: list<App\Entity\Party>, warehouses: list<App\Entity\Warehouse>, products: list<App\Entity\Product>} $options
 */

use App\Service\OrderLineValidator;

$common = ['old' => $old, 'errors' => $errors];
$customerOptions = ['' => '— Pilih customer —'];
foreach ($options['customers'] as $customer) {
    $customerOptions[(string) $customer->id] = $customer->name;
}
$warehouseOptions = ['' => '— Pilih gudang asal —'];
foreach ($options['warehouses'] as $warehouse) {
    $warehouseOptions[(string) $warehouse->id] = $warehouse->name;
}
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Buat Sales Order',
    'subtitle' => 'Disimpan sebagai Draft milik Anda; ajukan agar dapat disetujui Admin',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form wide animate-in" method="post" action="/sales-orders" data-stock-check>
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'required' => true, 'options' => $customerOptions, 'hint' => 'Hanya customer aktif.']) ?>
    <?= $view->partial('partials/form-field', $common + ['name' => 'warehouse_id', 'label' => 'Gudang Asal', 'type' => 'select', 'required' => true, 'options' => $warehouseOptions, 'hint' => 'Qty dicek terhadap stok gudang ini.']) ?>
  </div>
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'order_date', 'label' => 'Tanggal Order', 'type' => 'date', 'required' => true, 'hint' => 'Tidak boleh di masa depan.']) ?>
  </div>

  <?= $view->partial('partials/order-lines', [
      'lines' => $lines,
      'errors' => $errors,
      'products' => $options['products'],
      'priceField' => 'price',
      'priceLabel' => 'Harga Jual (Rp)',
      'defaultPrice' => 'sell',
      'showStock' => true,
      'maxLines' => OrderLineValidator::MAX_LINES,
  ]) ?>

  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan sebagai Draft</button>
    <a class="btn ghost" href="/sales-orders">Batal</a>
  </div>
</form>
