<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\UploadedFile;
use App\Service\ImageStorageException;
use App\Service\LocalImageStorage;
use PHPUnit\Framework\TestCase;

/**
 * PRD-01: tipe gambar ditentukan dari ISI file (magic bytes), bukan nama/ekstensi,
 * dan file disimpan dengan nama acak berekstensi pilihan server.
 */
final class LocalImageStorageTest extends TestCase
{
    // PNG 1x1 piksel yang valid.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $dir;
    /** @var list<string> */
    private array $temp = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ioms-upload-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach ([...$this->temp, ...(glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [])] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testValidPngIsAcceptedAndStoredUnderRandomNameWithServerExtension(): void
    {
        $storage = $this->storage();
        $file = $this->upload((string) base64_decode(self::PNG), 'foto.JPG');

        self::assertNull($storage->validate($file));
        $url = $storage->store($file);

        self::assertMatchesRegularExpression('#^/uploads/products/[0-9a-f]{32}\.png$#', $url);
        self::assertFileExists($this->dir . DIRECTORY_SEPARATOR . basename($url));
    }

    public function testPhpScriptRenamedToJpgIsRejectedByContent(): void
    {
        $file = $this->upload('<?php echo "pwned";', 'shell.jpg');

        self::assertSame('Format gambar harus JPG atau PNG.', $this->storage()->validate($file));
    }

    public function testStoreRefusesFileThatWouldFailValidation(): void
    {
        $this->expectException(ImageStorageException::class);

        $this->storage()->store($this->upload('bukan gambar', 'a.png'));
    }

    public function testSizeLimitFromPhpIniAndFromActualFileSize(): void
    {
        $storage = $this->storage(maxBytes: 1_048_576);
        $big = $this->upload(str_repeat('x', 1_048_577), 'besar.png');

        self::assertSame('Ukuran gambar maksimal 1 MB.', $storage->validate(new UploadedFile('', 'a.png', 0, UPLOAD_ERR_INI_SIZE)));
        // Ukuran dari klien bisa dipalsukan: ukuran file sebenarnya di disk tetap diperiksa.
        self::assertSame('Ukuran gambar maksimal 1 MB.', $storage->validate(new UploadedFile($big->tmpPath, 'besar.png', 10, UPLOAD_ERR_OK)));
    }

    public function testFileNotUploadedViaHttpOrWithUploadErrorIsRejected(): void
    {
        $png = (string) base64_decode(self::PNG);

        self::assertSame('Upload gambar gagal, silakan coba lagi.', $this->storage(uploaded: false)->validate($this->upload($png, 'a.png')));
        self::assertSame('Upload gambar gagal, silakan coba lagi.', $this->storage()->validate(new UploadedFile('', 'a.png', 0, UPLOAD_ERR_PARTIAL)));
    }

    public function testFailedMoveIsReported(): void
    {
        $storage = new LocalImageStorage($this->dir, '/uploads/products', 2_097_152, static fn (): bool => true, static fn (): bool => false);

        $this->expectException(ImageStorageException::class);
        $storage->store($this->upload((string) base64_decode(self::PNG), 'a.png'));
    }

    private function storage(int $maxBytes = 2_097_152, bool $uploaded = true): LocalImageStorage
    {
        return new LocalImageStorage(
            $this->dir,
            '/uploads/products/',
            $maxBytes,
            static fn (string $path): bool => $uploaded && is_file($path),
            static fn (string $from, string $to): bool => rename($from, $to),
        );
    }

    private function upload(string $content, string $clientName): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ioms');
        file_put_contents($path, $content);
        $this->temp[] = $path;

        return new UploadedFile($path, $clientName, strlen($content), UPLOAD_ERR_OK);
    }
}
