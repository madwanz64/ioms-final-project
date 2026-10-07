<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var list<App\Entity\User> $users
 */
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'User',
    'subtitle' => 'Akun hanya dibuat oleh Admin — tidak ada registrasi publik (USR-01)',
    'actions' => '<a class="btn primary" href="/users/create">+ Tambah User</a>',
]) ?>

<div class="table-wrap animate-in">
  <table class="data-table">
    <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= e($user->name) ?><?= $user->id === $currentUser->id ? ' <span class="text-muted">(Anda)</span>' : '' ?></td>
          <td><?= e($user->email) ?></td>
          <td><?= e($user->role->value) ?></td>
          <td><?= $view->partial('partials/status-badge', ['active' => $user->active]) ?></td>
          <td><a class="btn small" href="/users/<?= e($user->id) ?>/edit">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
