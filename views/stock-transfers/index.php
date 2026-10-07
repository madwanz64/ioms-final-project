<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Repository\PaginatedResult<App\Entity\TransferSummary> $result
 * @var App\Repository\OrderSearchCriteria $criteria
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Transfer Stok',
    'subtitle' => 'Perpindahan stok antar-gudang, tercatat di stock ledger sebagai Issue + Receipt',
    'actions' => '<a class="btn primary" href="/stock-transfers/create">+ Transfer Baru</a>',
]) ?>

<form class="toolbar animate-in" method="get" action="/stock-transfers" role="search">
  <label class="sr-only" for="f-q">Cari nomor transfer atau gudang</label>
  <input type="text" id="f-q" name="q" value="<?= e($criteria->search) ?>" placeholder="Cari no. transfer / gudang..." maxlength="100">
  <span class="spacer"></span>
  <label class="sr-only" for="f-sort">Urutan</label>
  <select id="f-sort" name="sort" data-auto-submit>
    <option value="date_desc"<?= $criteria->sort === 'date_desc' ? ' selected' : '' ?>>Tanggal: terbaru</option>
    <option value="date_asc"<?= $criteria->sort === 'date_asc' ? ' selected' : '' ?>>Tanggal: terlama</option>
  </select>
  <button type="submit" class="btn">Terapkan</button>
  <?php if ($criteria->hasFilters()): ?>
    <a class="btn ghost" href="/stock-transfers">Reset</a>
  <?php endif; ?>
</form>

<?php if ($result->items === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🔁</div>
      <?php if ($criteria->hasFilters()): ?>
        <h3>Tidak ada transfer yang cocok</h3>
        <p><a href="/stock-transfers">Tampilkan semua transfer</a>.</p>
      <?php else: ?>
        <h3>Belum ada transfer stok</h3>
        <p>Pindahkan stok dari gudang yang berlebih ke gudang yang kekurangan lewat tombol "+ Transfer Baru".</p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead>
        <tr><th>No. Transfer</th><th>Waktu</th><th>Dari</th><th>Ke</th><th class="num">Item</th><th class="num">Total Unit</th><th>Oleh</th><th><span class="sr-only">Aksi</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($result->items as $transfer): ?>
          <tr>
            <td><?= e($transfer->transferNo) ?></td>
            <td><?= e(date('d M Y H:i', (int) strtotime($transfer->createdAt))) ?></td>
            <td><?= e($transfer->fromWarehouseName) ?></td>
            <td><?= e($transfer->toWarehouseName) ?></td>
            <td class="num"><?= e($transfer->itemCount) ?></td>
            <td class="num"><?= e($transfer->totalQty) ?></td>
            <td><?= e($transfer->createdByName) ?></td>
            <td><a class="btn small" href="/stock-transfers/<?= e($transfer->id) ?>">Detail</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?= $view->partial('partials/pagination', ['result' => $result, 'basePath' => '/stock-transfers', 'query' => $criteria->toQuery()]) ?>
<?php endif; ?>
