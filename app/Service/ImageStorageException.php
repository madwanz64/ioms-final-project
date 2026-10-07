<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Gagal menyimpan file gambar ke storage (folder tidak bisa dibuat / file gagal dipindahkan).
 */
final class ImageStorageException extends RuntimeException
{
}
