<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\PartyType $type
 * @var App\Entity\Party|null $party
 * @var array<string, string> $old
 * @var array<string, string> $errors
 */
$label = $type->label();
$common = ['old' => $old, 'errors' => $errors];
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => ($party === null ? 'Tambah ' : 'Edit ') . $label,
    'subtitle' => $label . ' nonaktif tidak dapat dipilih pada order baru',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form animate-in" method="post" action="<?= e($party === null ? $type->path() : $type->path() . '/' . $party->id) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <?= $view->partial('partials/form-field', $common + ['name' => 'name', 'label' => 'Nama ' . $label, 'required' => true, 'maxlength' => 150]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'contact', 'label' => 'Kontak', 'required' => true, 'maxlength' => 100, 'hint' => 'Nomor telepon atau nama kontak.']) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'required' => true, 'maxlength' => 255]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'active', 'label' => 'Status', 'type' => 'select', 'options' => ['1' => 'Aktif', '0' => 'Nonaktif']]) ?>
  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan <?= e($label) ?></button>
    <a class="btn ghost" href="<?= e($type->path()) ?>">Batal</a>
  </div>
</form>
