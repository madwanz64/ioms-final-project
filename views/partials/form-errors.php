<?php
/**
 * @var array<string, string> $errors
 */
?>
<?php if ($errors !== []): ?>
  <div class="alert error" role="alert">Data belum disimpan. Periksa <?= e(count($errors)) ?> isian yang ditandai di bawah.</div>
<?php endif; ?>
