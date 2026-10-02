<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\UploadedFile;
use finfo;
use RuntimeException;

/**
 * Menyimpan gambar di public/uploads/products dengan nama acak (PRD-01).
 *
 * Tipe file diperiksa dari ISI file (magic bytes via finfo), bukan dari
 * ekstensi nama file atau header Content-Type yang dikirim browser — keduanya
 * bisa dipalsukan. Ekstensi file tersimpan ditentukan server dari MIME asli.
 */
final class LocalImageStorage implements ImageStorageInterface
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private readonly string $directory,
        private readonly string $urlPrefix,
        private readonly int $maxBytes,
    ) {
    }

    public function validate(UploadedFile $file): ?string
    {
        if ($file->error === UPLOAD_ERR_INI_SIZE || $file->error === UPLOAD_ERR_FORM_SIZE) {
            return $this->tooLargeMessage();
        }
        if (!$file->isOk() || !is_uploaded_file($file->tmpPath)) {
            return 'Upload gambar gagal, silakan coba lagi.';
        }
        if ($file->size > $this->maxBytes || (int) filesize($file->tmpPath) > $this->maxBytes) {
            return $this->tooLargeMessage();
        }
        if ($this->detectExtension($file) === null) {
            return 'Format gambar harus JPG atau PNG.';
        }

        return null;
    }

    public function store(UploadedFile $file): string
    {
        $extension = $this->detectExtension($file);
        if ($extension === null) {
            throw new RuntimeException('store() dipanggil untuk file yang tidak lolos validate().');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Folder upload tidak dapat dibuat.');
        }

        // 32 karakter heksadesimal acak (128 bit) — tidak bisa ditebak/di-enumerasi.
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file->tmpPath, $this->directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Gagal memindahkan file upload.');
        }

        return rtrim($this->urlPrefix, '/') . '/' . $filename;
    }

    private function detectExtension(UploadedFile $file): ?string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->tmpPath);

        return is_string($mime) ? (self::EXTENSIONS[$mime] ?? null) : null;
    }

    private function tooLargeMessage(): string
    {
        return sprintf('Ukuran gambar maksimal %s MB.', rtrim(rtrim(number_format($this->maxBytes / 1048576, 1), '0'), '.'));
    }
}
