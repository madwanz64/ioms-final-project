<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Service\TransactionManagerInterface;

/**
 * Menjalankan pekerjaan langsung (tanpa database). Mencatat jumlah transaksi
 * agar test bisa memastikan operasi stok memang dibungkus transaksi.
 */
final class ImmediateTransactionManager implements TransactionManagerInterface
{
    public int $runs = 0;

    public function run(callable $work): mixed
    {
        $this->runs++;

        return $work();
    }
}
