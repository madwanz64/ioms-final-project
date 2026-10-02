<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var array{activeProducts: int, lowStockCount: int, lowStock: list<App\Entity\ProductSummary>}|null $summary
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Dashboard ' . $currentUser->role->value,
    'subtitle' => 'Angka dihitung dari query agregasi database, bukan angka statis (DASH-01)',
]) ?>

<?php if ($summary === null): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🛒</div>
      <h3>Ringkasan order Anda segera hadir</h3>
      <p>Modul Sales Order sedang dibangun. Sementara itu Anda dapat melihat <a href="/products">katalog produk</a>.</p>
    </div>
  </div>
<?php else: ?>
  <div class="stat-grid stagger">
    <div class="stat-tile">
      <div class="value"><?= e($summary['activeProducts']) ?></div>
      <div class="label">Produk aktif</div>
    </div>
    <div class="stat-tile">
      <div class="value"><?= e($summary['lowStockCount']) ?></div>
      <div class="label">Produk di bawah reorder point</div>
    </div>
  </div>

  <div class="panel animate-in">
    <h3>Produk di bawah reorder point</h3>
    <?php if ($summary['lowStock'] === []): ?>
      <div class="empty-state">
        <div class="icon-box" aria-hidden="true">✓</div>
        <h3>Semua stok aman</h3>
        <p>Tidak ada produk aktif yang stoknya di bawah reorder point.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>SKU</th><th>Produk</th><th class="num">Stok</th><th class="num">Reorder</th></tr></thead>
          <tbody>
            <?php foreach ($summary['lowStock'] as $item): ?>
              <tr>
                <td><a href="/products/<?= e(rawurlencode($item->sku)) ?>"><?= e($item->sku) ?></a></td>
                <td><?= e($item->name) ?></td>
                <td class="num"><?= e($item->totalStock) ?></td>
                <td class="num"><?= e($item->reorderPoint) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($summary['lowStockCount'] > count($summary['lowStock'])): ?>
        <p class="page-subtitle"><a href="/products?stock=low&amp;sort=stock_asc">Lihat semua <?= e($summary['lowStockCount']) ?> produk low stock →</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
