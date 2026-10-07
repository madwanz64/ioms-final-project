<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var int $inventoryValue
 * @var array{activeProducts: int, lowStockCount: int, lowStock: list<App\Entity\ProductSummary>} $stock
 * @var list<App\Entity\StatusCount> $statusCounts
 * @var array{receipt: int, issue: int, days: int} $movement
 */

use App\Service\DashboardService;

$pendingApproval = DashboardService::countFor(array_values(array_filter($statusCounts, static fn ($c) => $c->orderType === 'SO')), ['PendingApproval']);
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Dashboard Admin',
    'subtitle' => 'Seluruh data — dihitung dari query agregasi yang sama dengan laporan CSV',
]) ?>

<div class="stat-grid stagger">
  <div class="stat-tile"><div class="value"><?= e(rupiah($inventoryValue)) ?></div><div class="label">Nilai inventori (stok × harga beli)</div></div>
  <div class="stat-tile"><div class="value"><?= e($stock['lowStockCount']) ?></div><div class="label">Produk di bawah reorder point</div></div>
  <div class="stat-tile"><div class="value"><?= e($pendingApproval) ?></div><div class="label"><a href="/sales-orders?status=PendingApproval">SO menunggu persetujuan</a></div></div>
  <div class="stat-tile"><div class="value">+<?= e($movement['receipt']) ?> / −<?= e($movement['issue']) ?></div><div class="label">Unit masuk / keluar <?= e($movement['days']) ?> hari terakhir</div></div>
</div>

<div class="two-col">
  <?= $view->partial('partials/low-stock', ['stock' => $stock]) ?>
  <section class="panel animate-in">
    <h3>Order per status</h3>
    <?= $view->partial('partials/status-counts', ['counts' => $statusCounts]) ?>
  </section>
</div>
