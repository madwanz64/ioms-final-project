<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Dilempar controller/router untuk kegagalan yang aman ditampilkan ke user
 * (403, 404, 422). Ditangkap front controller dan dirender sebagai halaman error.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }

    public static function forbidden(): self
    {
        return new self(403, 'Anda tidak memiliki hak akses untuk membuka halaman ini.');
    }

    public static function notFound(): self
    {
        return new self(404, 'Halaman atau data yang Anda cari tidak ditemukan.');
    }
}
