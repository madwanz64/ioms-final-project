<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var App\Entity\PartyType $type
 * @var list<App\Entity\Party> $parties
 */
$label = $type->label();
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $label,
    'subtitle' => $label . ' tidak dapat dihapus, hanya dinonaktifkan',
    'actions' => '<a class="btn primary" href="' . e($type->path()) . '/create">+ Tambah ' . e($label) . '</a>',
]) ?>

<?php if ($parties === []): ?>
  <div class="panel animate-in">
    <div class="empty-state">
      <div class="icon-box" aria-hidden="true">🏢</div>
      <h3>Belum ada <?= e(strtolower($label)) ?></h3>
      <p>Tambahkan <?= e(strtolower($label)) ?> pertama lewat tombol di kanan atas.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-wrap animate-in">
    <table class="data-table">
      <thead><tr><th>Nama</th><th>Kontak</th><th>Alamat</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
        <?php foreach ($parties as $party): ?>
          <tr>
            <td><?= e($party->name) ?></td>
            <td><?= e($party->contact) ?></td>
            <td><?= e($party->address) ?></td>
            <td><?= $view->partial('partials/status-badge', ['active' => $party->active]) ?></td>
            <td><a class="btn small" href="<?= e($type->path()) ?>/<?= e($party->id) ?>/edit">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
