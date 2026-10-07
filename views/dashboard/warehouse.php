<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var array{activeProducts: int, lowStockCount: int, lowStock: list<App\Entity\ProductSummary>} $stock
 * @var list<App\Entity\OrderSummary> $receiptQueue
 * @var list<App\Entity\OrderSummary> $issueQueue
 * @var int $receiptPending
 * @var int $issuePending
 * @var array{receipt: int, issue: int, days: int} $movement
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Dashboard Gudang',
    'subtitle' => 'Antrean goods receipt / goods issue dan produk low-stock',
]) ?>

<div class="stat-grid stagger">
  <div class="stat-tile"><div class="value"><?= e($receiptPending) ?></div><div class="label"><a href="/purchase-orders?status=Ordered">PO menunggu penerimaan</a></div></div>
  <div class="stat-tile"><div class="value"><?= e($issuePending) ?></div><div class="label"><a href="/sales-orders?status=Approved">SO menunggu goods issue</a></div></div>
  <div class="stat-tile"><div class="value"><?= e($stock['lowStockCount']) ?></div><div class="label">Produk di bawah reorder point</div></div>
  <div class="stat-tile"><div class="value">+<?= e($movement['receipt']) ?> / −<?= e($movement['issue']) ?></div><div class="label">Unit masuk / keluar <?= e($movement['days']) ?> hari terakhir</div></div>
</div>

<div class="two-col">
  <?= $view->partial('partials/order-queue', [
      'heading' => 'Antrean goods receipt (PO)',
      'orders' => $receiptQueue,
      'pending' => $receiptPending,
      'basePath' => '/purchase-orders',
      'emptyText' => 'Tidak ada PO yang menunggu penerimaan barang.',
  ]) ?>
  <?= $view->partial('partials/order-queue', [
      'heading' => 'Antrean goods issue (SO)',
      'orders' => $issueQueue,
      'pending' => $issuePending,
      'basePath' => '/sales-orders',
      'emptyText' => 'Tidak ada SO Approved yang menunggu dikirim.',
  ]) ?>
</div>

<?= $view->partial('partials/low-stock', ['stock' => $stock]) ?>
