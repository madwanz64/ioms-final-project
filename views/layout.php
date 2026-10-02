<?php
/**
 * Layout halaman terlindungi: sidebar sesuai role + area konten.
 *
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var string $currentPath
 * @var list<array{type: string, message: string}> $flashes
 * @var string $content
 * @var string|null $title
 */

use App\Entity\Role;

$navItems = match ($currentUser->role) {
    Role::Admin => [['/dashboard', 'Dashboard', 'dashboard'], ['/products', 'Produk', 'box']],
    Role::Sales => [['/dashboard', 'Dashboard', 'dashboard'], ['/products', 'Katalog Produk', 'box']],
    Role::WarehouseStaff => [['/dashboard', 'Dashboard', 'dashboard'], ['/products', 'Produk & Stok', 'box']],
};
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'IOMS') ?> — IOMS</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand"><span class="logo-dot"></span> IOMS</div>
      <span class="role-tag"><?= e($currentUser->role->value) ?></span>
      <nav aria-label="Navigasi utama">
        <?php foreach ($navItems as [$href, $label, $icon]): ?>
          <?php $active = $currentPath === $href || str_starts_with($currentPath, $href . '/'); ?>
          <a href="<?= e($href) ?>" class="<?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <span class="nav-icon"><?= $view->partial('partials/icon', ['name' => $icon]) ?></span><?= e($label) ?>
          </a>
        <?php endforeach; ?>
        <form method="post" action="/logout" class="logout-form">
          <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
          <button type="submit" class="logout-link">
            <span class="nav-icon"><?= $view->partial('partials/icon', ['name' => 'logout']) ?></span>Logout
          </button>
        </form>
      </nav>
    </aside>

    <main class="main">
      <?php foreach ($flashes as $flash): ?>
        <div class="alert <?= e($flash['type']) ?>" role="status" data-flash><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
  <script src="/js/app.js"></script>
</body>
</html>
