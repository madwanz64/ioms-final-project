<?php
/**
 * Tombol aksi ditentukan SalesOrderPolicy — aturan yang SAMA yang ditegakkan
 * SalesOrderService di server. Menyembunyikan tombol hanya kenyamanan UI.
 *
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\SalesOrder $order
 * @var list<App\Entity\StockMovement> $issues
 * @var App\Service\SalesOrderPolicy $policy
 */

use App\Entity\Role;
use App\Entity\SalesOrderStatus;

$base = '/sales-orders/' . $order->id;
$csrfField = '<input type="hidden" name="_csrf" value="' . e($csrfToken) . '">';
$canSubmit = $policy->canSubmit($currentUser, $order);
$canReview = $policy->canReview($currentUser, $order);
$canFulfill = $policy->canFulfill($currentUser, $order);
$canCancel = $policy->canCancel($currentUser, $order);
$ownPending = $order->status === SalesOrderStatus::PendingApproval && $order->isCreatedBy($currentUser);
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Sales Order ' . $order->orderNo,
    'subtitle' => $order->customerName . ' ← ' . $order->warehouseName,
    'actions' => '<a class="btn ghost" href="/sales-orders">← Kembali</a>',
]) ?>

<div class="two-col">
  <section class="panel animate-in">
    <h3>Informasi SO</h3>
    <table class="data-table" style="border:none;">
      <tbody>
        <tr><th scope="row">No. SO</th><td><?= e($order->orderNo) ?></td></tr>
        <tr><th scope="row">Status</th><td><span class="badge <?= e(strtolower($order->status->value)) ?>"><?= e($order->status->label()) ?></span></td></tr>
        <tr><th scope="row">Tanggal Order</th><td><?= e(date('d M Y', (int) strtotime($order->orderDate))) ?></td></tr>
        <tr><th scope="row">Customer</th><td><?= e($order->customerName) ?></td></tr>
        <tr><th scope="row">Gudang Asal</th><td><?= e($order->warehouseName) ?></td></tr>
        <tr><th scope="row">Dibuat oleh</th><td><?= e($order->createdByName) ?></td></tr>
        <tr><th scope="row">Disetujui oleh</th><td><?= e($order->approvedByName ?? '-') ?></td></tr>
        <tr><th scope="row">Total</th><td><?= e(rupiah($order->total())) ?></td></tr>
      </tbody>
    </table>

    <?php if ($canSubmit || $canReview || $canFulfill || $canCancel): ?>
      <div class="form-actions">
        <?php if ($canSubmit): ?>
          <form method="post" action="<?= e($base . '/submit') ?>"><?= $csrfField ?><button type="submit" class="btn primary">Ajukan untuk Persetujuan</button></form>
        <?php endif; ?>
        <?php if ($canReview): ?>
          <form method="post" action="<?= e($base . '/approve') ?>"><?= $csrfField ?><button type="submit" class="btn primary">Setujui</button></form>
          <form method="post" action="<?= e($base . '/reject') ?>" data-confirm="Tolak <?= e($order->orderNo) ?>? Order akan dibatalkan."><?= $csrfField ?><button type="submit" class="btn danger">Tolak</button></form>
        <?php endif; ?>
        <?php if ($canFulfill): ?>
          <form method="post" action="<?= e($base . '/fulfill') ?>" data-confirm="Proses goods issue <?= e($order->orderNo) ?>? Stok <?= e($order->warehouseName) ?> akan berkurang."><?= $csrfField ?><button type="submit" class="btn primary">Proses Goods Issue</button></form>
        <?php endif; ?>
        <?php if ($canCancel): ?>
          <form method="post" action="<?= e($base . '/cancel') ?>" data-confirm="Batalkan <?= e($order->orderNo) ?>?"><?= $csrfField ?><button type="submit" class="btn danger">Batalkan</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($ownPending && $currentUser->role === Role::Admin): ?>
      <p class="hint">Order ini Anda buat sendiri, sehingga harus disetujui Admin lain (segregation of duties).</p>
    <?php elseif ($ownPending): ?>
      <p class="hint">Menunggu persetujuan Admin. Pembuat order tidak dapat menyetujui order sendiri.</p>
    <?php endif; ?>
  </section>

  <section class="panel animate-in">
    <h3>Goods Issue (StockLedger)</h3>
    <?php if ($issues === []): ?>
      <p class="text-muted">Stok belum dikeluarkan untuk order ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Waktu</th><th>SKU</th><th>Gudang</th><th class="num">Qty</th></tr></thead>
          <tbody>
            <?php foreach ($issues as $movement): ?>
              <tr>
                <td><?= e(date('d M Y H:i', (int) strtotime($movement->createdAt))) ?></td>
                <td><?= e($movement->sku) ?></td>
                <td><?= e($movement->warehouseName) ?></td>
                <td class="num"><?= e($movement->quantity) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="panel animate-in">
  <h3>Item</h3>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Produk</th><th class="num">Qty</th><th class="num">Harga Jual</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($order->items as $item): ?>
          <tr>
            <td><a href="/products/<?= e(rawurlencode($item->sku)) ?>"><?= e($item->sku) ?></a> — <?= e($item->productName) ?></td>
            <td class="num"><?= e($item->qty) ?> <?= e($item->unit) ?></td>
            <td class="num"><?= e(rupiah($item->price)) ?></td>
            <td class="num"><?= e(rupiah($item->subtotal())) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
