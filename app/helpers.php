<?php

declare(strict_types=1);

/**
 * Escape untuk konteks HTML (teks & atribut). Dipakai untuk SEMUA nilai
 * dinamis di views/ (§4.2: output user di-escape sebelum ditampilkan).
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
