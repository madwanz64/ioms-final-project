<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Batas transaksi yang dipakai Service tanpa bergantung pada PDO (ARCH-01).
 * Implementasi produksi: App\Repository\PdoTransactionManager.
 */
interface TransactionManagerInterface
{
    /**
     * Jalankan $work dalam satu transaksi: commit bila selesai, rollback bila
     * ada exception (exception diteruskan ke pemanggil).
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
