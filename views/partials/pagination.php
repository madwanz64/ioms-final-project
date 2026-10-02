<?php
/**
 * Pagination berbasis link. Seluruh query string aktif (search/filter/sort)
 * ikut dibawa sehingga filter tetap berlaku saat pindah halaman (FIND-01).
 *
 * @var App\Repository\PaginatedResult<mixed> $result
 * @var string $basePath
 * @var array<string, string> $query
 */
$pages = $result->totalPages();
$link = static fn (int $page): string => $basePath . '?' . http_build_query(array_merge($query, ['page' => $page]));
?>
<nav class="pagination" aria-label="Pagination">
  <span class="info">Menampilkan <?= e($result->firstItemNumber()) ?>–<?= e($result->lastItemNumber()) ?> dari <?= e($result->total) ?></span>
  <?php if ($result->page > 1): ?>
    <a href="<?= e($link($result->page - 1)) ?>" rel="prev">‹ Sebelumnya</a>
  <?php else: ?>
    <span class="disabled">‹ Sebelumnya</span>
  <?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $result->page): ?>
      <span class="active" aria-current="page"><?= e($i) ?></span>
    <?php else: ?>
      <a href="<?= e($link($i)) ?>"><?= e($i) ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($result->page < $pages): ?>
    <a href="<?= e($link($result->page + 1)) ?>" rel="next">Berikutnya ›</a>
  <?php else: ?>
    <span class="disabled">Berikutnya ›</span>
  <?php endif; ?>
</nav>
