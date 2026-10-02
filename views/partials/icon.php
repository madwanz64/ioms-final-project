<?php
/**
 * Ikon SVG inline (dibuat sendiri, sama dengan prototype). Nilai $name
 * hanya berasal dari kode, bukan dari input user.
 *
 * @var string $name
 */
$icons = [
    'dashboard' => '<rect x="2.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="2.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/>',
    'box' => '<path d="M10 2.5 17 6.25v7.5L10 17.5 3 13.75v-7.5L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3 6.25 10 10l7-3.75M10 10v7.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
    'logout' => '<path d="M7.5 17.5h-3a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 13.5 17 10l-4-3.5M17 10H7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
];
?>
<svg viewBox="0 0 20 20" width="18" height="18" fill="none" aria-hidden="true"><?= $icons[$name] ?? '' ?></svg>
