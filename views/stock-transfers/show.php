<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Entity\StockTransfer $transfer
 * @var list<App\Entity\StockMovement> $movements
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Transfer ' . $transfer->transferNo,
    'subtitle' => $transfer->fromWarehouseName . ' → ' . $transfer->toWarehouseName,
    'actions' => '<a class="btn ghost" href="/stock-transfers">← Kembali</a>',
]) ?>

<div class="two-col">
  <section class="panel animate-in">
    <h3>Informasi Transfer</h3>
    <table class="data-table info-table">
      <tbody>
        <tr><th scope="row">No. Transfer</th><td><?= e($transfer->transferNo) ?></td></tr>
        <tr><th scope="row">Waktu</th><td><?= e(date('d M Y H:i', (int) strtotime($transfer->createdAt))) ?></td></tr>
        <tr><th scope="row">Dari Gudang</th><td><?= e($transfer->fromWarehouseName) ?></td></tr>
        <tr><th scope="row">Ke Gudang</th><td><?= e($transfer->toWarehouseName) ?></td></tr>
        <tr><th scope="row">Dilakukan oleh</th><td><?= e($transfer->createdByName) ?></td></tr>
        <tr><th scope="row">Catatan</th><td><?= e($transfer->note ?? '-') ?></td></tr>
        <tr><th scope="row">Total Unit</th><td><?= e($transfer->totalQty()) ?></td></tr>
      </tbody>
    </table>
  </section>

  <section class="panel animate-in">
    <h3>Item</h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Produk</th><th class="num">Qty</th></tr></thead>
        <tbody>
          <?php foreach ($transfer->items as $item): ?>
            <tr>
              <td><a href="/products/<?= e(rawurlencode($item->sku)) ?>"><?= e($item->sku) ?></a> — <?= e($item->productName) ?></td>
              <td class="num"><?= e($item->qty) ?> <?= e($item->unit) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="panel animate-in">
  <h3>Pergerakan di Stock Ledger</h3>
  <p class="hint">Setiap item tercatat dua kali dalam satu transaksi: keluar (Issue) dari gudang asal dan masuk (Receipt) ke gudang tujuan.</p>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Waktu</th><th>SKU</th><th>Gudang</th><th>Tipe</th><th class="num">Qty</th></tr></thead>
      <tbody>
        <?php foreach ($movements as $movement): ?>
          <tr>
            <td><?= e(date('d M Y H:i', (int) strtotime($movement->createdAt))) ?></td>
            <td><?= e($movement->sku) ?></td>
            <td><?= e($movement->warehouseName) ?></td>
            <td><span class="badge <?= e(strtolower($movement->type)) ?>"><?= e($movement->type) ?></span></td>
            <td class="num"><?= e(($movement->quantity > 0 ? '+' : '') . $movement->quantity) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
