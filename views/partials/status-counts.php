<?php
/**
 * Tabel jumlah & nilai order per status (dashboard & laporan memakai data yang sama).
 *
 * @var list<App\Entity\StatusCount> $counts
 * @var bool|null $showType
 */

use App\Entity\PurchaseOrderStatus;
use App\Entity\SalesOrderStatus;

$showType ??= true;
$label = static fn (App\Entity\StatusCount $c): string => $c->orderType === 'PO'
    ? (PurchaseOrderStatus::tryFrom($c->status)?->label() ?? $c->status)
    : (SalesOrderStatus::tryFrom($c->status)?->label() ?? $c->status);
?>
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><?= $showType ? '<th>Jenis</th>' : '' ?><th>Status</th><th class="num">Jumlah</th><th class="num">Nilai</th></tr>
    </thead>
    <tbody>
      <?php foreach ($counts as $count): ?>
        <tr>
          <?php if ($showType): ?><td><?= e($count->orderType) ?></td><?php endif; ?>
          <td><span class="badge <?= e(strtolower($count->status)) ?>"><?= e($label($count)) ?></span></td>
          <td class="num"><?= e($count->count) ?></td>
          <td class="num"><?= e(rupiah($count->total)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
