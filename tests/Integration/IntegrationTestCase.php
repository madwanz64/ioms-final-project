<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Basis integration test (TEST-02): MySQL sungguhan pada database terpisah
 * (DB_TEST_NAME, default ioms_test) yang dibangun ulang dari
 * database/schema-and-seed.sql satu kali per proses PHPUnit.
 *
 * Setiap test dibungkus transaksi lalu di-rollback, sehingga test independen
 * dan bisa dijalankan dalam urutan acak (FIRST: Independent, Repeatable).
 */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $schemaLoaded = false;
    private static ?PDO $sharedPdo = null;

    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = self::connection();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function connection(): PDO
    {
        if (self::$sharedPdo !== null) {
            return self::$sharedPdo;
        }

        /** @var array{db: array{host: string, port: int, name: string, user: string, pass: string}, db_test_name: string} $config */
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $testDb = $config['db_test_name'];

        // Pengaman: jangan pernah membangun ulang database aplikasi.
        if ($testDb === $config['db']['name'] || !str_ends_with($testDb, '_test')) {
            throw new RuntimeException('DB_TEST_NAME harus berakhiran "_test" dan berbeda dari DB_NAME.');
        }

        if (!self::$schemaLoaded) {
            self::loadSchema($config['db'], $testDb);
            self::$schemaLoaded = true;
        }

        self::$sharedPdo = Database::connect(['name' => $testDb] + $config['db']);

        return self::$sharedPdo;
    }

    /**
     * @param array{host: string, port: int, name: string, user: string, pass: string} $db
     */
    private static function loadSchema(array $db, string $testDb): void
    {
        $server = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . $testDb . '` CHARACTER SET utf8mb4');
        $server->exec('USE `' . $testDb . '`');

        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema-and-seed.sql');
        // Skema asli memilih database "ioms"; baris itu dibuang agar semua tabel masuk ke database test.
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
                && !preg_match('/^\s*(CREATE DATABASE|USE)\b/i', $line)
        );
        foreach (preg_split('/;\s*$/m', implode("\n", $lines)) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $server->exec($statement);
            }
        }
    }
}
