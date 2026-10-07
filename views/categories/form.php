<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\Category|null $category
 * @var array<string, string> $old
 * @var array<string, string> $errors
 */
$common = ['old' => $old, 'errors' => $errors];
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $category === null ? 'Tambah Kategori' : 'Edit Kategori',
    'subtitle' => 'Nama kategori harus unik',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form animate-in" method="post" action="<?= e($category === null ? '/categories' : '/categories/' . $category->id) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <?= $view->partial('partials/form-field', $common + ['name' => 'name', 'label' => 'Nama Kategori', 'required' => true, 'maxlength' => 100]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'maxlength' => 255, 'hint' => 'Opsional.']) ?>
  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan Kategori</button>
    <a class="btn ghost" href="/categories">Batal</a>
  </div>
</form>
