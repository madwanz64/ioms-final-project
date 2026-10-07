<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string|null $error
 */
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Inventory &amp; Order Management</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <div class="logo-dot"></div>
      <h1>Inventory &amp; Order Management</h1>
      <p class="sub">Masuk menggunakan akun yang diberikan Admin.</p>

      <?php if ($error !== null): ?>
        <div class="alert error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="data-form" method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div class="field">
          <label for="email">Email <span class="req">*</span></label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="nama@ioms.test"
                 autocomplete="username" required maxlength="150" autofocus>
        </div>
        <div class="field">
          <label for="password">Password <span class="req">*</span></label>
          <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn primary btn-block">Masuk</button>
        </div>
      </form>

      <div class="demo-accounts">
        <strong>Akun demo (data seed) — klik untuk menyalin:</strong>
        <table>
          <tr><td>Admin</td><td><code class="copyable" title="Klik untuk menyalin">admin@ioms.test</code></td><td><code class="copyable" title="Klik untuk menyalin">admin123</code></td></tr>
          <tr><td>Sales</td><td><code class="copyable" title="Klik untuk menyalin">sinta@ioms.test</code></td><td><code class="copyable" title="Klik untuk menyalin">sales123</code></td></tr>
          <tr><td>Warehouse</td><td><code class="copyable" title="Klik untuk menyalin">rudi@ioms.test</code></td><td><code class="copyable" title="Klik untuk menyalin">gudang123</code></td></tr>
        </table>
      </div>
    </div>
  </div>
  <script src="/js/app.js"></script>
</body>
</html>
