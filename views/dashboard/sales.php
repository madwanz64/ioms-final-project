<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var list<App\Entity\StatusCount> $statusCounts
 */
$byStatus = [];
foreach ($statusCounts as $count) {
    $byStatus[$count->status] = $count;
}
$total = array_sum(array_map(static fn ($c): int => $c->count, $statusCounts));
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Dashboard Sales',
    'subtitle' => 'Ringkasan order milik Anda per status',
    'actions' => '<a class="btn primary" href="/sales-orders/create">+ Buat SO</a>',
]) ?>

<div class="stat-grid stagger">
  <div class="stat-tile"><div class="value"><?= e($byStatus['Draft']->count ?? 0) ?></div><div class="label"><a href="/sales-orders?status=Draft">Draft (belum diajukan)</a></div></div>
  <div class="stat-tile"><div class="value"><?= e($byStatus['PendingApproval']->count ?? 0) ?></div><div class="label"><a href="/sales-orders?status=PendingApproval">Menunggu persetujuan</a></div></div>
  <div class="stat-tile"><div class="value"><?= e($byStatus['Approved']->count ?? 0) ?></div><div class="label"><a href="/sales-orders?status=Approved">Disetujui, menunggu dikirim</a></div></div>
  <div class="stat-tile"><div class="value"><?= e(rupiah($byStatus['Fulfilled']->total ?? 0)) ?></div><div class="label">Nilai order Fulfilled</div></div>
</div>

<section class="panel animate-in">
  <h3>Order saya per status (total <?= e($total) ?>)</h3>
  <?php if ($total === 0): ?>
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🛒</div>
      <h3>Belum ada Sales Order</h3>
      <p><a href="/sales-orders/create">Buat Sales Order pertama</a> dari katalog produk.</p>
    </div>
  <?php else: ?>
    <?= $view->partial('partials/status-counts', ['counts' => $statusCounts, 'showType' => false]) ?>
  <?php endif; ?>
</section>
