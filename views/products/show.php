<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Entity\Product $product
 * @var string $category
 * @var list<App\Entity\StockLevel> $stockLevels
 * @var list<App\Entity\StockMovement> $movements
 * @var bool $canSeeBuyPrice
 */

use App\Entity\Role;

$totalStock = array_sum(array_map(static fn ($level): int => $level->quantity, $stockLevels));
$isLow = $totalStock < $product->reorderPoint;
$actions = $currentUser->role === Role::Admin
    ? '<a class="btn primary" href="/products/' . e(rawurlencode($product->sku)) . '/edit">Edit Produk</a>'
    : '';
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $product->name,
    'subtitle' => $product->sku . ' · ' . $category,
    'actions' => '<a class="btn ghost" href="/products">← Kembali</a>' . $actions,
]) ?>

<div class="two-col">
  <section class="panel animate-in">
    <h3>Informasi Produk</h3>
    <div style="display:flex; gap:18px; flex-wrap:wrap;">
      <div class="img-drop" style="cursor:default;">
        <?php if ($product->imageUrl !== null): ?>
          <img src="<?= e($product->imageUrl) ?>" alt="Gambar <?= e($product->name) ?>">
        <?php else: ?>
          <span>Tidak ada<br>gambar</span>
        <?php endif; ?>
      </div>
      <table class="data-table" style="border:none; flex:1; min-width:220px;">
        <tbody>
          <tr><th scope="row">SKU</th><td><?= e($product->sku) ?></td></tr>
          <tr><th scope="row">Kategori</th><td><?= e($category) ?></td></tr>
          <tr><th scope="row">Unit</th><td><?= e($product->unit) ?></td></tr>
          <?php if ($canSeeBuyPrice): ?>
            <tr><th scope="row">Harga Beli</th><td><?= e(rupiah($product->buyPrice)) ?></td></tr>
          <?php endif; ?>
          <tr><th scope="row">Harga Jual</th><td><?= e(rupiah($product->sellPrice)) ?></td></tr>
          <tr><th scope="row">Reorder Point</th><td><?= e($product->reorderPoint) ?></td></tr>
          <tr>
            <th scope="row">Status</th>
            <td>
              <span class="badge <?= $product->active ? 'active' : 'inactive' ?>"><?= $product->active ? 'Aktif' : 'Nonaktif' ?></span>
              <?php if ($isLow): ?><span class="badge lowstock">Low Stock</span><?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel animate-in">
    <h3>Stok per Gudang — total <?= e($totalStock) ?> <?= e($product->unit) ?></h3>
    <?php if ($stockLevels === []): ?>
      <p class="text-muted">Belum ada baris stok untuk produk ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Gudang</th><th class="num">Quantity</th></tr></thead>
          <tbody>
            <?php foreach ($stockLevels as $level): ?>
              <tr><td><?= e($level->warehouseName) ?></td><td class="num"><?= e($level->quantity) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="panel animate-in">
  <h3>Riwayat Pergerakan Stok (StockLedger) — 10 terbaru</h3>
  <?php if ($movements === []): ?>
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🗒</div>
      <h3>Belum ada pergerakan stok</h3>
      <p>Pergerakan akan tercatat otomatis saat ada goods receipt (PO) atau goods issue (SO).</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Tanggal</th><th>Gudang</th><th>Tipe</th><th class="num">Qty</th><th>Referensi</th></tr></thead>
        <tbody>
          <?php foreach ($movements as $movement): ?>
            <tr>
              <td><?= e(date('d M Y H:i', (int) strtotime($movement->createdAt))) ?></td>
              <td><?= e($movement->warehouseName) ?></td>
              <td><span class="badge <?= e(strtolower($movement->type)) ?>"><?= e($movement->type) ?></span></td>
              <td class="num"><?= e(($movement->quantity > 0 ? '+' : '') . $movement->quantity) ?></td>
              <td><?= e($movement->refId) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
