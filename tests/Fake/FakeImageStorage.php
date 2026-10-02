<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Core\UploadedFile;
use App\Service\ImageStorageInterface;

/**
 * Pengganti LocalImageStorage di unit test: tidak menyentuh filesystem.
 * File dianggap tidak valid bila nama client-nya mengandung "invalid".
 */
final class FakeImageStorage implements ImageStorageInterface
{
    /** @var list<string> */
    public array $stored = [];

    public function validate(UploadedFile $file): ?string
    {
        return str_contains($file->clientName, 'invalid') ? 'Format gambar harus JPG atau PNG.' : null;
    }

    public function store(UploadedFile $file): string
    {
        $url = '/uploads/products/fake-' . count($this->stored) . '.png';
        $this->stored[] = $url;

        return $url;
    }
}
