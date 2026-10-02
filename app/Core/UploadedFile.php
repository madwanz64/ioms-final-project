<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representasi satu file dari $_FILES, supaya Service tidak menyentuh
 * superglobal secara langsung (ARCH-01).
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $clientName,
        public readonly int $size,
        public readonly int $error,
    ) {
    }

    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
}
