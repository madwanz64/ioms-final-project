<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\UploadedFile;
use Closure;
use finfo;

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

    /** @var Closure(string): bool */
    private readonly Closure $isUploaded;

    /** @var Closure(string, string): bool */
    private readonly Closure $moveUploaded;

    /**
     * @param (Closure(string): bool)|null $isUploaded default is_uploaded_file
     * @param (Closure(string, string): bool)|null $moveUploaded default move_uploaded_file
     *        Keduanya hanya diganti di test: di CLI tidak ada file hasil upload HTTP.
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $urlPrefix,
        private readonly int $maxBytes,
        ?Closure $isUploaded = null,
        ?Closure $moveUploaded = null,
    ) {
        $this->isUploaded = $isUploaded ?? is_uploaded_file(...);
        $this->moveUploaded = $moveUploaded ?? move_uploaded_file(...);
    }

    public function validate(UploadedFile $file): ?string
    {
        // Urutan pemeriksaan penting: file yang gagal di-upload tidak diperiksa ukuran/isinya.
        return match (true) {
            $file->error === UPLOAD_ERR_INI_SIZE || $file->error === UPLOAD_ERR_FORM_SIZE => $this->tooLargeMessage(),
            !$file->isOk() || !($this->isUploaded)($file->tmpPath) => 'Upload gambar gagal, silakan coba lagi.',
            $file->size > $this->maxBytes || (int) filesize($file->tmpPath) > $this->maxBytes => $this->tooLargeMessage(),
            $this->detectExtension($file) === null => 'Format gambar harus JPG atau PNG.',
            default => null,
        };
    }

    public function store(UploadedFile $file): string
    {
        $extension = $this->detectExtension($file);
        if ($extension === null) {
            throw new ImageStorageException('store() dipanggil untuk file yang tidak lolos validate().');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new ImageStorageException('Folder upload tidak dapat dibuat.');
        }

        // 32 karakter heksadesimal acak (128 bit) — tidak bisa ditebak/di-enumerasi.
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!($this->moveUploaded)($file->tmpPath, $this->directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new ImageStorageException('Gagal memindahkan file upload.');
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
