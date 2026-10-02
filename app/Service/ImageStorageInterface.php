<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\UploadedFile;

/**
 * Penyimpanan gambar produk (PRD-01). Dipisah dari ProductService agar
 * aturan produk bisa diuji tanpa filesystem / upload HTTP sungguhan.
 */
interface ImageStorageInterface
{
    /**
     * Pesan error bila file tidak valid (tipe/ukuran), atau null bila valid.
     */
    public function validate(UploadedFile $file): ?string;

    /**
     * Simpan file dengan nama acak dan kembalikan URL publiknya.
     */
    public function store(UploadedFile $file): string;
}
