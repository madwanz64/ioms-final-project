<?php
/**
 * @var array{activeProducts: int, lowStockCount: int, lowStock: list<App\Entity\ProductSummary>} $stock
 */
?>
<section class="panel animate-in">
  <h3>Produk di bawah reorder point</h3>
  <?php if ($stock['lowStock'] === []): ?>
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
          <?php foreach ($stock['lowStock'] as $item): ?>
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
    <?php if ($stock['lowStockCount'] > count($stock['lowStock'])): ?>
      <p class="page-subtitle"><a href="/products?stock=low&amp;sort=stock_asc">Lihat semua <?= e($stock['lowStockCount']) ?> produk low stock →</a></p>
    <?php endif; ?>
  <?php endif; ?>
</section>
