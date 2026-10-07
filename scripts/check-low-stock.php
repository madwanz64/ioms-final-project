<?php

declare(strict_types=1);

/**
 * JOB-01 — Script terjadwal: ringkasan produk di bawah reorder point.
 *
 * Dipisahkan dari siklus request web (di dunia nyata dijalankan cron), jadi
 * tidak bisa dipanggil lewat browser. Cara menjalankan:
 *
 *   php scripts/check-low-stock.php
 *   docker compose exec app php scripts/check-low-stock.php
 *
 * Contoh jadwal cron (tidak wajib di server penilaian):
 *   0 7 * * 1-5  cd /var/www/html && php scripts/check-low-stock.php >> var/log/low-stock.log
 *
 * Exit code: 0 = berhasil (ada atau tidak ada produk low stock), 1 = gagal (mis. database).
 */

use App\Core\Database;
use App\Entity\LowStockLine;
use App\Entity\StockLevel;
use App\Repository\MySqlProductRepository;
use App\Service\LowStockReportService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * str_pad yang menghitung karakter multibyte dengan benar.
 */
function pad(string $text, int $width, int $type = STR_PAD_RIGHT): string
{
    $text = mb_strlen($text) > $width ? mb_substr($text, 0, $width - 1) . '…' : $text;

    return str_pad($text, $width + strlen($text) - mb_strlen($text), ' ', $type);
}

try {
    $config = require dirname(__DIR__) . '/config/config.php';
    $service = new LowStockReportService(new MySqlProductRepository(Database::connect($config['db'])));
    $lines = $service->lines();
} catch (Throwable $e) {
    fwrite(STDERR, '[check-low-stock] Gagal: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Ringkasan produk di bawah reorder point — ' . date('Y-m-d H:i') . PHP_EOL;
echo str_repeat('=', 96) . PHP_EOL;

if ($lines === []) {
    echo 'Semua produk aktif berada di atas reorder point. Tidak ada yang perlu dipesan ulang.' . PHP_EOL;
    exit(0);
}

echo pad('SKU', 10) . pad('Produk', 24) . pad('Stok', 6, STR_PAD_LEFT) . pad('Reorder', 9, STR_PAD_LEFT)
    . pad('Kurang', 8, STR_PAD_LEFT) . '  Per gudang' . PHP_EOL;
echo str_repeat('-', 96) . PHP_EOL;

foreach ($lines as $line) {
    /** @var LowStockLine $line */
    $perWarehouse = implode(', ', array_map(
        static fn (StockLevel $level): string => $level->warehouseName . ': ' . $level->quantity,
        $line->levels,
    ));
    echo pad($line->sku, 10) . pad($line->name, 24) . pad((string) $line->totalStock, 6, STR_PAD_LEFT)
        . pad((string) $line->reorderPoint, 9, STR_PAD_LEFT) . pad((string) $line->shortage(), 8, STR_PAD_LEFT)
        . '  ' . $perWarehouse . PHP_EOL;
}

echo str_repeat('-', 96) . PHP_EOL;
printf(
    "Total: %d produk perlu dipesan ulang (kekurangan minimal %d unit). Buat Purchase Order dari menu Purchase Order.\n",
    count($lines),
    array_sum(array_map(static fn (LowStockLine $l): int => $l->shortage(), $lines)),
);
exit(0);
