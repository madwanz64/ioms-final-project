<?php
/**
 * @var string $heading
 * @var list<App\Entity\OrderSummary> $orders
 * @var int $pending
 * @var string $basePath "/purchase-orders" | "/sales-orders"
 * @var string $emptyText
 */
?>
<section class="panel animate-in">
  <h3><?= e($heading) ?> <span class="badge pendingapproval"><?= e($pending) ?></span></h3>
  <?php if ($orders === []): ?>
    <p class="text-muted"><?= e($emptyText) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>No.</th><th>Tanggal</th><th>Pihak</th><th>Gudang</th><th><span class="sr-only">Aksi</span></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= e($order->orderNo) ?></td>
              <td><?= e(date('d M Y', (int) strtotime($order->orderDate))) ?></td>
              <td><?= e($order->partyName) ?></td>
              <td><?= e($order->warehouseName) ?></td>
              <td><a class="btn small" href="<?= e($basePath . '/' . $order->id) ?>">Proses</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
