<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Entity\DateRange $range
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var list<string> $available
 * @var array{statusCounts: list<App\Entity\StatusCount>, movements: list<App\Entity\MovementSummary>, ledger: list<App\Entity\LedgerEntry>} $preview
 */

use App\Entity\Role;
use App\Service\ReportService;

$query = http_build_query(['from' => $range->fromDate(), 'to' => $range->toDate()]);
$csvLink = static fn (string $type, string $label): string => '<a class="btn small" href="/reports/' . e($type) . '.csv?' . e($query) . '">⬇ ' . e($label) . '</a>';
$common = ['old' => $old, 'errors' => $errors];
$ledgerShown = array_slice($preview['ledger'], -20);
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Laporan',
    'subtitle' => $currentUser->role === Role::Sales ? 'Status order milik Anda' : 'Dihitung dari query rekap yang sama dengan dashboard (REPORT-01)',
]) ?>

<form class="toolbar animate-in" method="get" action="/reports">
  <?= $view->partial('partials/form-field', $common + ['name' => 'from', 'label' => 'Dari tanggal', 'type' => 'date', 'required' => true]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'to', 'label' => 'Sampai tanggal', 'type' => 'date', 'required' => true]) ?>
  <button type="submit" class="btn">Tampilkan</button>
  <span class="hint">Maksimal <?= e(ReportService::MAX_DAYS) ?> hari. Menampilkan <?= e($range->fromDate()) ?> s.d. <?= e($range->toDate()) ?>.</span>
</form>

<?php if (in_array(ReportService::ORDER_STATUS, $available, true)): ?>
  <section class="panel animate-in">
    <h3>Status order (tanggal order dalam rentang)</h3>
    <div class="form-actions mt-0"><?= $csvLink(ReportService::ORDER_STATUS, 'Unduh CSV status order') ?></div>
    <?= $view->partial('partials/status-counts', ['counts' => $preview['statusCounts']]) ?>
  </section>
<?php endif; ?>

<?php if (in_array(ReportService::STOCK_SUMMARY, $available, true)): ?>
  <section class="panel animate-in">
    <h3>Rekap pergerakan stok per produk & gudang</h3>
    <div class="form-actions mt-0">
      <?= $csvLink(ReportService::STOCK_SUMMARY, 'Unduh CSV rekap stok') ?>
      <?= $csvLink(ReportService::STOCK_LEDGER, 'Unduh CSV detail stock ledger') ?>
    </div>
    <?php if ($preview['movements'] === []): ?>
      <div class="empty-state">
        <div class="icon-box" aria-hidden="true">🗒</div>
        <h3>Tidak ada pergerakan stok</h3>
        <p>Tidak ada baris stock ledger pada rentang tanggal ini. Coba perlebar rentangnya.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>SKU</th><th>Produk</th><th>Gudang</th><th class="num">Receipt</th><th class="num">Issue</th><th class="num">Adjustment</th><th class="num">Netto</th></tr></thead>
          <tbody>
            <?php foreach ($preview['movements'] as $m): ?>
              <tr>
                <td><?= e($m->sku) ?></td><td><?= e($m->productName) ?></td><td><?= e($m->warehouseName) ?></td>
                <td class="num"><?= e($m->receipt) ?></td><td class="num"><?= e($m->issue) ?></td>
                <td class="num"><?= e($m->adjustment) ?></td><td class="num"><?= e($m->net()) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel animate-in">
    <h3>Stock ledger — <?= e(count($ledgerShown)) ?> baris terakhir dari <?= e(count($preview['ledger'])) ?></h3>
    <?php if ($preview['ledger'] === []): ?>
      <p class="text-muted">Tidak ada baris ledger pada rentang ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Waktu</th><th>SKU</th><th>Gudang</th><th>Tipe</th><th class="num">Qty</th><th>Referensi</th><th>Oleh</th></tr></thead>
          <tbody>
            <?php foreach ($ledgerShown as $entry): ?>
              <tr>
                <td><?= e(date('d M Y H:i', (int) strtotime($entry->createdAt))) ?></td>
                <td><?= e($entry->sku) ?></td><td><?= e($entry->warehouseName) ?></td>
                <td><span class="badge <?= e(strtolower($entry->movementType)) ?>"><?= e($entry->movementType) ?></span></td>
                <td class="num"><?= e(($entry->quantity > 0 ? '+' : '') . $entry->quantity) ?></td>
                <td><?= e($entry->refId) ?></td><td><?= e($entry->performedBy) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
