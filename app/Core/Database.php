<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Database
{
    /**
     * @param array{host: string, port: int, name: string, user: string, pass: string} $config
     */
    public static function connect(array $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']);

        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statement asli di sisi MySQL, bukan emulasi string di PHP.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Samakan zona waktu sesi MySQL dengan PHP, agar CURRENT_TIMESTAMP (created_at
        // ledger) dan filter tanggal laporan konsisten walau server MySQL berjalan di UTC.
        $offset = (new \DateTimeImmutable())->format('P'); // mis. "+07:00", bukan input user
        $pdo->prepare('SET time_zone = :offset')->execute(['offset' => $offset]);

        return $pdo;
    }

    private static int $savepointDepth = 0;

    /**
     * Menjalankan $work dalam satu transaksi eksplisit (DB-01).
     *
     * Bila sudah ada transaksi aktif (blok bersarang, mis. repository yang dipanggil
     * di dalam transaksi service, atau integration test yang membungkus tiap test),
     * $work dijalankan dalam SAVEPOINT: bila gagal, HANYA pekerjaan blok ini yang
     * dibatalkan (ROLLBACK TO SAVEPOINT) lalu exception diteruskan, sehingga perilakunya
     * sama dengan transaksi tunggal — tidak ada tulisan setengah jadi yang tertinggal.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function transactional(PDO $pdo, callable $work): mixed
    {
        if ($pdo->inTransaction()) {
            $savepoint = 'sp_' . ++self::$savepointDepth;
            $pdo->exec('SAVEPOINT ' . $savepoint); // nama dari counter internal, bukan input
            try {
                $result = $work();
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);

                return $result;
            } catch (Throwable $e) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                throw $e;
            } finally {
                self::$savepointDepth--;
            }
        }

        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
