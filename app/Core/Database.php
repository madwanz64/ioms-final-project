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

    /**
     * Menjalankan $work dalam satu transaksi eksplisit (DB-01).
     * Bila sudah ada transaksi aktif (mis. integration test yang membungkus
     * tiap test dengan rollback), $work ikut transaksi luar itu.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function transactional(PDO $pdo, callable $work): mixed
    {
        if ($pdo->inTransaction()) {
            return $work();
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
