<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Validasi gagal (VAL-01). Membawa SEMUA pesan error sekaligus (per field),
 * supaya user bisa memperbaiki semuanya dalam satu kali submit.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors field => pesan
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validasi gagal: ' . implode(', ', array_keys($errors)));
    }
}
