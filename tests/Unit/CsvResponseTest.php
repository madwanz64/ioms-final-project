<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Area logic: keamanan & format file CSV (REPORT-01).
 */
final class CsvResponseTest extends TestCase
{
    /**
     * @return array<string, array{string|int, string}>
     */
    public static function cells(): array
    {
        return [
            'rumus Excel dinetralkan' => ['=HYPERLINK("http://jahat.test")', '\'=HYPERLINK("http://jahat.test")'],
            'diawali plus' => ['+62 812', "'+62 812"],
            'diawali minus (teks)' => ['-cmd', "'-cmd"],
            'diawali @' => ['@SUM(A1)', "'@SUM(A1)"],
            'angka negatif tetap angka' => [-4, '-4'],
            'teks biasa tidak diubah' => ['Kabel HDMI 2m', 'Kabel HDMI 2m'],
        ];
    }

    #[DataProvider('cells')]
    public function testCellsThatExcelWouldEvaluateAreEscaped(string|int $input, string $expected): void
    {
        self::assertSame($expected, Response::csvCell($input));
    }

    public function testCsvHasUtf8BomQuotesAndDownloadHeaders(): void
    {
        $response = Response::csv('rekap stok/../x.csv', [['Produk', 'Qty'], ['Kertas "A4", 80gr', -4]]);

        self::assertStringStartsWith("\xEF\xBB\xBF", $response->body);
        self::assertStringContainsString('"Kertas ""A4"", 80gr",-4', $response->body);
        self::assertSame('text/csv; charset=utf-8', $response->headers['Content-Type']);
        // Karakter berbahaya di nama file (spasi, /) diganti, jadi header tidak bisa dimanipulasi.
        self::assertSame('attachment; filename="rekap_stok_.._x.csv"', $response->headers['Content-Disposition']);
    }
}
