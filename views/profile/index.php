<?php
/**
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var App\Entity\User $profile
 * @var array<string, string> $old
 * @var array<string, string> $errors
 */

use App\Service\UserService;

$common = ['old' => $old, 'errors' => $errors];
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => 'Profil Saya',
    'subtitle' => 'Email, role, dan status akun hanya dapat diubah oleh Admin',
]) ?>
<?= $view->partial('partials/form-errors', ['errors' => $errors]) ?>

<form class="data-form animate-in" method="post" action="/profile" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

  <div class="form-row">
    <div class="field">
      <label for="f-email">Email</label>
      <input type="email" id="f-email" value="<?= e($profile->email) ?>" readonly>
    </div>
    <div class="field">
      <label for="f-role">Role</label>
      <input type="text" id="f-role" value="<?= e($profile->role->value) ?>" readonly>
    </div>
  </div>

  <?= $view->partial('partials/form-field', $common + ['name' => 'name', 'label' => 'Nama', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'name']) ?>

  <fieldset class="line-items">
    <legend>Ganti password (opsional)</legend>
    <p class="hint">Kosongkan ketiga kolom bila tidak ingin mengganti password.</p>
    <?= $view->partial('partials/form-field', $common + ['name' => 'current_password', 'label' => 'Password saat ini', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
    <div class="form-row">
      <?= $view->partial('partials/form-field', $common + ['name' => 'password', 'label' => 'Password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => sprintf('Minimal %d karakter.', UserService::MIN_PASSWORD_LENGTH)]) ?>
      <?= $view->partial('partials/form-field', $common + ['name' => 'password_confirmation', 'label' => 'Konfirmasi password baru', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
    </div>
  </fieldset>

  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan Profil</button>
    <a class="btn ghost" href="/dashboard">Batal</a>
  </div>
</form>
