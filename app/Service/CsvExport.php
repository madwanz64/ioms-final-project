<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Isi file CSV yang siap dikirim: nama file + baris (baris pertama = header).
 */
final class CsvExport
{
    /**
     * @param list<list<string|int>> $rows
     */
    public function __construct(
        public readonly string $filename,
        public readonly array $rows,
    ) {
    }
}
