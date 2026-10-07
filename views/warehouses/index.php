<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var list<App\Entity\Warehouse> $warehouses
 * @var array<int, int> $stockTotals
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Gudang',
    'subtitle' => 'Setiap produk memiliki baris stok di setiap gudang (WH-01)',
    'actions' => '<a class="btn primary" href="/warehouses/create">+ Tambah Gudang</a>',
]) ?>

<?php if ($warehouses === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🏬</div>
      <h3>Belum ada gudang</h3>
      <p>Tambahkan gudang agar stok produk dapat dicatat per lokasi.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead><tr><th>Nama</th><th>Lokasi</th><th class="num">Total Unit Stok</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($warehouses as $warehouse): ?>
          <tr>
            <td><?= e($warehouse->name) ?></td>
            <td><?= e($warehouse->location) ?></td>
            <td class="num"><?= e($stockTotals[$warehouse->id] ?? 0) ?></td>
            <td><?= $view->partial('partials/status-badge', ['active' => $warehouse->active]) ?></td>
            <td><a class="btn small" href="/warehouses/<?= e($warehouse->id) ?>/edit">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
