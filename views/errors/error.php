<?php
/**
 * Halaman error mandiri (ERR-01). Tidak memakai layout supaya tetap bisa
 * dirender walau koneksi database/session gagal.
 *
 * @var int $status
 * @var string $message
 */
$headings = [403 => 'Akses Ditolak', 404 => 'Tidak Ditemukan', 405 => 'Metode Tidak Didukung', 422 => 'Data Tidak Valid', 500 => 'Terjadi Kesalahan'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($status) ?> — IOMS</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <div class="error-shell">
    <div>
      <div class="code"><?= e($status) ?></div>
      <h1><?= e($headings[$status] ?? 'Terjadi Kesalahan') ?></h1>
      <p class="page-subtitle"><?= e($message) ?></p>
      <div class="form-actions" style="justify-content:center; margin-top:16px;">
        <a class="btn primary" href="/dashboard">Kembali ke Dashboard</a>
      </div>
    </div>
  </div>
</body>
</html>
