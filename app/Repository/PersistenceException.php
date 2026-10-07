<?php

declare(strict_types=1);

namespace App\Repository;

use RuntimeException;

/**
 * Kegagalan lapisan penyimpanan yang menandakan kesalahan pemakaian atau data tidak konsisten
 * (mis. lock dipanggil di luar transaksi, baris stok hilang). Tidak ditampilkan ke user:
 * ditangkap handler global, dicatat ke log, user melihat halaman error 500 (ERR-01).
 */
final class PersistenceException extends RuntimeException
{
}
