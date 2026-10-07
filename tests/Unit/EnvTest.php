<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

/**
 * Konfigurasi environment (§5.1): .env lokal vs environment variable container.
 */
final class EnvTest extends TestCase
{
    public function testParsesKeyValueLinesIgnoringCommentsAndQuotes(): void
    {
        $values = Env::parse("# komentar\nDB_HOST=127.0.0.1\n\nDB_PASS=\"rahasia dev\"\nRUSAK\nAPP_TIMEZONE = Asia/Jakarta\n");

        self::assertSame(['DB_HOST' => '127.0.0.1', 'DB_PASS' => 'rahasia dev', 'APP_TIMEZONE' => 'Asia/Jakarta'], $values);
    }

    public function testRealEnvironmentVariablesWinEvenWithoutEnvFile(): void
    {
        // Kondisi di container: tidak ada file .env, konfigurasi datang dari Docker Compose.
        putenv('IOMS_TEST_ONLY_KEY=dari-compose');
        try {
            $values = Env::load(__DIR__ . '/tidak-ada.env');

            self::assertSame('dari-compose', $values['IOMS_TEST_ONLY_KEY'] ?? null);
        } finally {
            putenv('IOMS_TEST_ONLY_KEY');
        }
    }
}
