<?php
/**
 * @var App\Entity\User $currentUser
 * @var string $title
 * @var string $subtitle
 * @var string $actions HTML tombol aksi (sudah di-escape oleh pemanggil)
 */
?>
<div class="topbar animate-in">
  <div>
    <h1 class="page-title"><?= e($title) ?></h1>
    <?php if (($subtitle ?? '') !== ''): ?>
      <div class="page-subtitle"><?= e($subtitle) ?></div>
    <?php endif; ?>
  </div>
  <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
    <?= $actions ?? '' ?>
    <a class="topbar-user" href="/profile" title="Profil saya">
      <div>
        <span class="name" style="display:block; text-align:right;"><?= e($currentUser->name) ?></span>
        <span class="role"><?= e($currentUser->role->value) ?></span>
      </div>
      <div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($currentUser->name, 0, 1))) ?></div>
    </a>
  </div>
</div>
