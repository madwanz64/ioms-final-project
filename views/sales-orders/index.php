<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Repository\PaginatedResult<App\Entity\OrderSummary> $result
 * @var App\Repository\OrderSearchCriteria $criteria
 * @var bool $canCreate
 */

use App\Entity\Role;
use App\Entity\SalesOrderStatus;

$subtitle = match ($currentUser->role) {
    Role::Sales => 'Hanya order yang Anda buat',
    Role::WarehouseStaff => 'Order yang sudah diajukan; proses goods issue untuk order Approved',
    Role::Admin => 'Seluruh order; setujui atau tolak order yang menunggu persetujuan',
};
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $currentUser->role === Role::Sales ? 'Sales Order Saya' : 'Sales Order',
    'subtitle' => $subtitle,
    'actions' => $canCreate ? '<a class="btn primary" href="/sales-orders/create">+ Buat SO</a>' : '',
]) ?>

<form class="toolbar animate-in" method="get" action="/sales-orders" role="search">
  <label class="sr-only" for="f-q">Cari nomor SO atau customer</label>
  <input type="text" id="f-q" name="q" value="<?= e($criteria->search) ?>" placeholder="Cari no. SO / customer..." maxlength="100">
  <label class="sr-only" for="f-status">Status</label>
  <select id="f-status" name="status" data-auto-submit>
    <option value="">Semua Status</option>
    <?php foreach (SalesOrderStatus::cases() as $status): ?>
      <?php if ($status === SalesOrderStatus::Draft && $currentUser->role === Role::WarehouseStaff) {
          continue;
      } ?>
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
    <a class="btn ghost" href="/sales-orders">Reset</a>
  <?php endif; ?>
</form>

<?php if ($result->items === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🛒</div>
      <?php if ($criteria->hasFilters()): ?>
        <h3>Tidak ada SO yang cocok</h3>
        <p>Ubah kata kunci atau filter, atau <a href="/sales-orders">tampilkan semua SO</a>.</p>
      <?php else: ?>
        <h3>Belum ada Sales Order</h3>
        <p><?= $canCreate ? 'Buat Sales Order pertama dari katalog produk.' : 'Belum ada order yang perlu diproses.' ?></p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead>
        <tr><th>No. SO</th><th>Tanggal</th><th>Customer</th><th>Gudang Asal</th><th class="num">Item</th><th class="num">Total</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($result->items as $order): ?>
          <?php $status = SalesOrderStatus::from($order->status); ?>
          <tr>
            <td><?= e($order->orderNo) ?></td>
            <td><?= e(date('d M Y', (int) strtotime($order->orderDate))) ?></td>
            <td><?= e($order->partyName) ?></td>
            <td><?= e($order->warehouseName) ?></td>
            <td class="num"><?= e($order->itemCount) ?></td>
            <td class="num"><?= e(rupiah($order->total)) ?></td>
            <td><span class="badge <?= e(strtolower($status->value)) ?>"><?= $status === SalesOrderStatus::PendingApproval ? '<span class="dot"></span>' : '' ?><?= e($status->label()) ?></span></td>
            <td><a class="btn small" href="/sales-orders/<?= e($order->id) ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= $view->partial('partials/pagination', ['result' => $result, 'basePath' => '/sales-orders', 'query' => $criteria->toQuery()]) ?>
<?php endif; ?>
