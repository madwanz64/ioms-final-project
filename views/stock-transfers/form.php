<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var array<string, string> $old
 * @var list<array<string, string>> $lines
 * @var array<string, string> $errors
 * @var array{warehouses: list<App\Entity\Warehouse>, products: list<App\Entity\Product>} $options
 */

use App\Service\OrderLineValidator;

$common = ['old' => $old, 'errors' => $errors];
$fromOptions = ['' => '— Pilih gudang asal —'];
$toOptions = ['' => '— Pilih gudang tujuan —'];
foreach ($options['warehouses'] as $warehouse) {
    $fromOptions[(string) $warehouse->id] = $warehouse->name;
    $toOptions[(string) $warehouse->id] = $warehouse->name;
}
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Transfer Stok Baru',
    'subtitle' => 'Dijalankan langsung dalam satu transaksi; ditolak seluruhnya bila stok gudang asal tidak cukup',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form wide animate-in" method="post" action="/stock-transfers" data-stock-check="from_warehouse_id">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'from_warehouse_id', 'label' => 'Dari Gudang', 'type' => 'select', 'required' => true, 'options' => $fromOptions, 'hint' => 'Stok tersedia di gudang ini tampil per baris.']) ?>
    <?= $view->partial('partials/form-field', $common + ['name' => 'to_warehouse_id', 'label' => 'Ke Gudang', 'type' => 'select', 'required' => true, 'options' => $toOptions, 'hint' => 'Harus berbeda dari gudang asal.']) ?>
  </div>
  <?= $view->partial('partials/form-field', $common + ['name' => 'note', 'label' => 'Catatan', 'maxlength' => 255, 'hint' => 'Opsional, mis. alasan pemindahan.']) ?>

  <?= $view->partial('partials/order-lines', [
      'lines' => $lines,
      'errors' => $errors,
      'products' => $options['products'],
      'priceField' => null,
      'showStock' => true,
      'maxLines' => OrderLineValidator::MAX_LINES,
  ]) ?>

  <div class="form-actions">
    <button type="submit" class="btn primary">Pindahkan Stok</button>
    <a class="btn ghost" href="/stock-transfers">Batal</a>
  </div>
</form>
