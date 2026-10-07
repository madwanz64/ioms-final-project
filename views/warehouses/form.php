<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\Warehouse|null $warehouse
 * @var array<string, string> $old
 * @var array<string, string> $errors
 */
$common = ['old' => $old, 'errors' => $errors];
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $warehouse === null ? 'Tambah Gudang' : 'Edit Gudang',
    'subtitle' => $warehouse === null ? 'Gudang baru otomatis mendapat baris stok 0 untuk setiap produk' : 'Gudang tidak dapat dihapus, hanya dinonaktifkan',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form animate-in" method="post" action="<?= e($warehouse === null ? '/warehouses' : '/warehouses/' . $warehouse->id) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <?= $view->partial('partials/form-field', $common + ['name' => 'name', 'label' => 'Nama Gudang', 'required' => true, 'maxlength' => 150]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'location', 'label' => 'Lokasi', 'required' => true, 'maxlength' => 255]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'active', 'label' => 'Status', 'type' => 'select', 'options' => ['1' => 'Aktif', '0' => 'Nonaktif']]) ?>
  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan Gudang</button>
    <a class="btn ghost" href="/warehouses">Batal</a>
  </div>
</form>
