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
    'tag' => '<path d="M10.5 2.5h4.5a1 1 0 0 1 1 1v4.5a1 1 0 0 1-.3.7l-8 8a1 1 0 0 1-1.4 0l-4.7-4.7a1 1 0 0 1 0-1.4l8-8a1 1 0 0 1 .7-.3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="13" cy="6" r="1.1" fill="currentColor"/>',
    'warehouse' => '<path d="M2 8.5 10 3l8 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.5 8v8h13V8" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 16v-4h4v4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
    'building' => '<rect x="3" y="2.5" width="10" height="15" rx="1" stroke="currentColor" stroke-width="1.6"/><path d="M6 6h1M9.5 6h1M6 9h1M9.5 9h1M6 12h1M9.5 12h1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 9h2.5a1 1 0 0 1 1 1v7.5H13" stroke="currentColor" stroke-width="1.6"/>',
    'users' => '<circle cx="7.5" cy="6.5" r="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M2.5 16.5c0-2.8 2.2-5 5-5s5 2.2 5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="14.5" cy="7" r="2" stroke="currentColor" stroke-width="1.6"/><path d="M13 11.8c1.9.4 3.5 2 3.5 4.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    'truck' => '<rect x="1.5" y="5" width="9.5" height="7.5" rx="1" stroke="currentColor" stroke-width="1.6"/><path d="M11 8h3.2L17 10.7V12.5h-6V8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="5" cy="14.5" r="1.5" stroke="currentColor" stroke-width="1.6"/><circle cx="14" cy="14.5" r="1.5" stroke="currentColor" stroke-width="1.6"/>',
    'logout' => '<path d="M7.5 17.5h-3a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 13.5 17 10l-4-3.5M17 10H7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
];
?>
<svg viewBox="0 0 20 20" width="18" height="18" fill="none" aria-hidden="true"><?= $icons[$name] ?? '' ?></svg>
