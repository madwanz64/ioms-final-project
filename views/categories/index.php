<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var list<App\Entity\Category> $categories
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Kategori Produk',
    'subtitle' => 'Dipakai untuk mengelompokkan produk dan filter katalog',
    'actions' => '<a class="btn primary" href="/categories/create">+ Tambah Kategori</a>',
]) ?>

<?php if ($categories === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🏷</div>
      <h3>Belum ada kategori</h3>
      <p>Tambahkan kategori pertama sebelum membuat produk.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead><tr><th>Nama</th><th>Deskripsi</th><th><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($categories as $category): ?>
          <tr>
            <td><?= e($category->name) ?></td>
            <td><?= e($category->description ?? '-') ?></td>
            <td><a class="btn small" href="/categories/<?= e($category->id) ?>/edit">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
