<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\User|null $user
 * @var array<string, string> $old
 * @var array<string, string> $errors
 */

use App\Entity\Role;
use App\Service\UserService;

$isEdit = $user !== null;
$isSelf = $isEdit && $user->id === $currentUser->id;
$common = ['old' => $old, 'errors' => $errors];
$roles = ['' => '— Pilih role —'];
foreach (Role::cases() as $role) {
    $roles[$role->value] = $role->value;
}
$passwordHint = sprintf('Minimal %d karakter.', UserService::MIN_PASSWORD_LENGTH) . ($isEdit ? ' Kosongkan bila tidak ingin mengganti password.' : '');
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $isEdit ? 'Edit User' : 'Tambah User',
    'subtitle' => $isSelf ? 'Ini akun Anda sendiri: role dan status tidak dapat diubah' : 'Email harus unik',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form animate-in" method="post" action="<?= e($isEdit ? '/users/' . $user->id : '/users') ?>" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
  <?= $view->partial('partials/form-field', $common + ['name' => 'name', 'label' => 'Nama', 'required' => true, 'maxlength' => 150]) ?>
  <?= $view->partial('partials/form-field', $common + ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'off']) ?>
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'options' => $roles]) ?>
    <?= $view->partial('partials/form-field', $common + ['name' => 'active', 'label' => 'Status', 'type' => 'select', 'options' => ['1' => 'Aktif', '0' => 'Nonaktif'], 'hint' => 'User nonaktif tidak dapat login.']) ?>
  </div>
  <div class="form-row">
    <?= $view->partial('partials/form-field', $common + ['name' => 'password', 'label' => $isEdit ? 'Password Baru' : 'Password', 'type' => 'password', 'required' => !$isEdit, 'autocomplete' => 'new-password', 'hint' => $passwordHint]) ?>
    <?= $view->partial('partials/form-field', $common + ['name' => 'password_confirmation', 'label' => 'Konfirmasi Password', 'type' => 'password', 'required' => !$isEdit, 'autocomplete' => 'new-password']) ?>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan User</button>
    <a class="btn ghost" href="/users">Batal</a>
  </div>
</form>
