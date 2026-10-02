<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Repository\PaginatedResult<App\Entity\ProductSummary> $result
 * @var App\Repository\ProductSearchCriteria $criteria
 * @var list<App\Entity\Category> $categories
 */

use App\Entity\Role;

$isAdmin = $currentUser->role === Role::Admin;
$query = array_filter([
    'q' => $criteria->search,
    'category' => $criteria->categoryId === null ? '' : (string) $criteria->categoryId,
    'stock' => $criteria->stockStatus,
    'sort' => $criteria->sort,
], static fn (string $v): bool => $v !== '');
$sortLabels = ['name_asc' => 'Urutkan: Nama A-Z', 'name_desc' => 'Nama Z-A', 'stock_asc' => 'Stok Terendah'];
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $currentUser->role === Role::Sales ? 'Katalog Produk' : 'Daftar Produk',
    'subtitle' => 'Stok total = jumlah stok seluruh gudang',
    'actions' => $isAdmin ? '<a class="btn primary" href="/products/create">+ Tambah Produk</a>' : '',
]) ?>

<form class="toolbar animate-in" method="get" action="/products" role="search">
  <label class="sr-only" for="f-search">Cari produk</label>
  <input type="text" id="f-search" name="q" value="<?= e($criteria->search) ?>" placeholder="Cari nama / SKU..." maxlength="100">
  <label class="sr-only" for="f-category">Kategori</label>
  <select id="f-category" name="category" data-auto-submit>
    <option value="">Semua Kategori</option>
    <?php foreach ($categories as $category): ?>
      <option value="<?= e($category->id) ?>"<?= $criteria->categoryId === $category->id ? ' selected' : '' ?>><?= e($category->name) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="sr-only" for="f-stock">Status stok</label>
  <select id="f-stock" name="stock" data-auto-submit>
    <option value="">Semua Status Stok</option>
    <option value="low"<?= $criteria->stockStatus === 'low' ? ' selected' : '' ?>>Low Stock</option>
    <option value="normal"<?= $criteria->stockStatus === 'normal' ? ' selected' : '' ?>>Normal</option>
  </select>
  <span class="spacer"></span>
  <label class="sr-only" for="f-sort">Urutan</label>
  <select id="f-sort" name="sort" data-auto-submit>
    <?php foreach ($sortLabels as $value => $label): ?>
      <option value="<?= e($value) ?>"<?= $criteria->sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn">Terapkan</button>
  <?php if ($criteria->hasFilters()): ?>
    <a class="btn ghost" href="/products">Reset</a>
  <?php endif; ?>
</form>

<?php if ($result->items === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">📦</div>
      <?php if ($criteria->hasFilters()): ?>
        <h3>Tidak ada produk yang cocok</h3>
        <p>Coba ubah kata kunci atau filter, atau <a href="/products">tampilkan semua produk</a>.</p>
      <?php else: ?>
        <h3>Belum ada produk</h3>
        <p><?= $isAdmin ? 'Tambahkan produk pertama lewat tombol "+ Tambah Produk".' : 'Katalog produk masih kosong.' ?></p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead>
        <tr>
          <th>SKU</th><th>Nama Produk</th><th>Kategori</th>
          <th class="num">Harga Jual</th><th class="num">Stok Total</th><th>Status</th><th><span class="sr-only">Aksi</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result->items as $item): ?>
          <tr>
            <td><?= e($item->sku) ?></td>
            <td><?= e($item->name) ?></td>
            <td><?= e($item->categoryName) ?></td>
            <td class="num"><?= e(rupiah($item->sellPrice)) ?></td>
            <td class="num"><?= e($item->totalStock) ?></td>
            <td>
              <?php if ($item->isLowStock()): ?>
                <span class="badge lowstock">Low Stock</span>
              <?php else: ?>
                <span class="badge active">Normal</span>
              <?php endif; ?>
              <?php if (!$item->active): ?>
                <span class="badge inactive">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td><a class="btn small" href="/products/<?= e(rawurlencode($item->sku)) ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= $view->partial('partials/pagination', ['result' => $result, 'basePath' => '/products', 'query' => $query]) ?>
<?php endif; ?>
