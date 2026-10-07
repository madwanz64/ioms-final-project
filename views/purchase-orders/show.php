<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\PurchaseOrder $order
 * @var list<App\Entity\StockMovement> $receipts
 * @var bool $canOrder
 * @var bool $canCancel
 * @var bool $canReceive
 * @var array<array-key, string> $receiveOld
 * @var array<string, string> $errors
 */
$base = '/purchase-orders/' . $order->id;
$csrfField = '<input type="hidden" name="_csrf" value="' . e($csrfToken) . '">';
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Purchase Order ' . $order->orderNo,
    'subtitle' => $order->supplierName . ' → ' . $order->warehouseName,
    'actions' => '<a class="btn ghost" href="/purchase-orders">← Kembali</a>',
]) ?>

<div class="two-col">
  <section class="panel animate-in">
    <h3>Informasi PO</h3>
    <table class="data-table info-table">
      <tbody>
        <tr><th scope="row">No. PO</th><td><?= e($order->orderNo) ?></td></tr>
        <tr><th scope="row">Status</th><td><span class="badge <?= e(strtolower($order->status->value)) ?>"><?= e($order->status->label()) ?></span></td></tr>
        <tr><th scope="row">Tanggal Order</th><td><?= e(date('d M Y', (int) strtotime($order->orderDate))) ?></td></tr>
        <tr><th scope="row">Supplier</th><td><?= e($order->supplierName) ?></td></tr>
        <tr><th scope="row">Gudang Tujuan</th><td><?= e($order->warehouseName) ?></td></tr>
        <tr><th scope="row">Dibuat oleh</th><td><?= e($order->createdByName) ?></td></tr>
        <tr><th scope="row">Total</th><td><?= e(rupiah($order->total())) ?></td></tr>
      </tbody>
    </table>
    <?php if ($canOrder || $canCancel): ?>
      <div class="form-actions">
        <?php if ($canOrder): ?>
          <form method="post" action="<?= e($base . '/order') ?>"><?= $csrfField ?><button type="submit" class="btn primary">Tandai Ordered</button></form>
        <?php endif; ?>
        <?php if ($canCancel): ?>
          <form method="post" action="<?= e($base . '/cancel') ?>" data-confirm="Batalkan <?= e($order->orderNo) ?>? Tindakan ini tidak dapat dibatalkan."><?= $csrfField ?><button type="submit" class="btn danger">Batalkan PO</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel animate-in">
    <h3>Riwayat Penerimaan (StockLedger)</h3>
    <?php if ($receipts === []): ?>
      <p class="text-muted">Belum ada barang yang diterima untuk PO ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Waktu</th><th>SKU</th><th>Gudang</th><th class="num">Qty</th></tr></thead>
          <tbody>
            <?php foreach ($receipts as $movement): ?>
              <tr>
                <td><?= e(date('d M Y H:i', (int) strtotime($movement->createdAt))) ?></td>
                <td><?= e($movement->sku) ?></td>
                <td><?= e($movement->warehouseName) ?></td>
                <td class="num">+<?= e($movement->quantity) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="panel animate-in">
  <h3><?= $canReceive ? 'Item & Penerimaan Barang (Goods Receipt)' : 'Item' ?></h3>
  <?php if ($errors !== []): ?>
    <div class="alert error" role="alert"><?= e($errors['receive'] ?? 'Penerimaan belum disimpan. Periksa qty yang ditandai.') ?></div>
  <?php endif; ?>
  <form method="post" action="<?= e($base . '/receive') ?>">
    <?= $csrfField ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Produk</th><th class="num">Harga Beli</th><th class="num">Dipesan</th><th class="num">Diterima</th><th class="num">Sisa</th>
            <?php if ($canReceive): ?><th class="num">Terima Sekarang</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($order->items as $item): ?>
            <?php $field = 'receive.' . $item->id; ?>
            <tr>
              <td><a href="/products/<?= e(rawurlencode($item->sku)) ?>"><?= e($item->sku) ?></a> — <?= e($item->productName) ?></td>
              <td class="num"><?= e(rupiah($item->buyPrice)) ?></td>
              <td class="num"><?= e($item->qty) ?> <?= e($item->unit) ?></td>
              <td class="num"><?= e($item->receivedQty) ?></td>
              <td class="num"><?= e($item->remainingQty()) ?></td>
              <?php if ($canReceive): ?>
                <td class="num">
                  <?php if ($item->remainingQty() > 0): ?>
                    <label class="sr-only" for="receive-<?= e($item->id) ?>">Qty diterima <?= e($item->sku) ?></label>
                    <input type="number" id="receive-<?= e($item->id) ?>" name="receive[<?= e($item->id) ?>]" value="<?= e($receiveOld[$item->id] ?? '') ?>"
                           min="0" max="<?= e($item->remainingQty()) ?>" step="1" inputmode="numeric" style="max-width:110px;"<?= isset($errors[$field]) ? ' aria-invalid="true"' : '' ?>>
                    <?php if (isset($errors[$field])): ?><span class="error-msg"><?= e($errors[$field]) ?></span><?php endif; ?>
                  <?php else: ?>
                    <span class="badge received">Lengkap</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($canReceive): ?>
      <div class="form-actions">
        <button type="submit" class="btn primary">Simpan Penerimaan</button>
        <span class="hint">Penerimaan sebagian diperbolehkan; stok gudang <?= e($order->warehouseName) ?> bertambah dan tercatat di ledger dalam satu transaksi.</span>
      </div>
    <?php endif; ?>
  </form>
</section>
