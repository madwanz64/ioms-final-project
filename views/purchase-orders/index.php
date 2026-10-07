<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Repository\PaginatedResult<App\Entity\OrderSummary> $result
 * @var App\Repository\OrderSearchCriteria $criteria
 */

use App\Entity\PurchaseOrderStatus;
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Purchase Order',
    'subtitle' => 'Pembelian ke supplier & penerimaan barang (PO-01)',
    'actions' => '<a class="btn primary" href="/purchase-orders/create">+ Buat PO</a>',
]) ?>

<form class="toolbar animate-in" method="get" action="/purchase-orders" role="search">
  <label class="sr-only" for="f-q">Cari nomor PO atau supplier</label>
  <input type="text" id="f-q" name="q" value="<?= e($criteria->search) ?>" placeholder="Cari no. PO / supplier..." maxlength="100">
  <label class="sr-only" for="f-status">Status</label>
  <select id="f-status" name="status" data-auto-submit>
    <option value="">Semua Status</option>
    <?php foreach (PurchaseOrderStatus::cases() as $status): ?>
      <option value="<?= e($status->value) ?>"<?= $criteria->status === $status->value ? ' selected' : '' ?>><?= e($status->label()) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="spacer"></span>
  <label class="sr-only" for="f-sort">Urutan</label>
  <select id="f-sort" name="sort" data-auto-submit>
    <option value="date_desc"<?= $criteria->sort === 'date_desc' ? ' selected' : '' ?>>Tanggal: terbaru</option>
    <option value="date_asc"<?= $criteria->sort === 'date_asc' ? ' selected' : '' ?>>Tanggal: terlama</option>
  </select>
  <button type="submit" class="btn">Terapkan</button>
  <?php if ($criteria->hasFilters()): ?>
    <a class="btn ghost" href="/purchase-orders">Reset</a>
  <?php endif; ?>
</form>

<?php if ($result->items === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🚚</div>
      <?php if ($criteria->hasFilters()): ?>
        <h3>Tidak ada PO yang cocok</h3>
        <p>Ubah kata kunci atau filter, atau <a href="/purchase-orders">tampilkan semua PO</a>.</p>
      <?php else: ?>
        <h3>Belum ada Purchase Order</h3>
        <p>Buat PO saat stok produk mendekati atau di bawah reorder point.</p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead>
        <tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th>Gudang Tujuan</th><th class="num">Item</th><th class="num">Total</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($result->items as $order): ?>
          <?php $status = PurchaseOrderStatus::from($order->status); ?>
          <tr>
            <td><?= e($order->orderNo) ?></td>
            <td><?= e(date('d M Y', (int) strtotime($order->orderDate))) ?></td>
            <td><?= e($order->partyName) ?></td>
            <td><?= e($order->warehouseName) ?></td>
            <td class="num"><?= e($order->itemCount) ?></td>
            <td class="num"><?= e(rupiah($order->total)) ?></td>
            <td><span class="badge <?= e(strtolower($status->value)) ?>"><?= e($status->label()) ?></span></td>
            <td><a class="btn small" href="/purchase-orders/<?= e($order->id) ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= $view->partial('partials/pagination', ['result' => $result, 'basePath' => '/purchase-orders', 'query' => $criteria->toQuery()]) ?>
<?php endif; ?>
