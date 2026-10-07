<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\StockChange;
use App\Repository\InsufficientStockException;
use App\Repository\StockRepositoryInterface;

/**
 * Menerapkan perubahan stok sesuai ADR-001. Dipanggil oleh service order
 * (goods receipt PO, goods issue SO) DI DALAM transaksi milik pemanggil,
 * sehingga perubahan status order, item, stok, dan ledger commit bersama.
 */
final class StockService
{
    public function __construct(private readonly StockRepositoryInterface $stock)
    {
    }

    /**
     * 1) kunci semua baris stok terkait (urutan tetap), 2) tolak bila ada
     * pengurangan yang melebihi stok terkunci, 3) tulis ledger + stok.
     *
     * @param list<StockChange> $changes
     * @throws BusinessRuleException stok tidak mencukupi
     */
    public function apply(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $keys = [];
        $netDelta = [];
        foreach ($changes as $change) {
            $key = StockChange::key($change->sku, $change->warehouseId);
            $keys[$key] = ['sku' => $change->sku, 'warehouseId' => $change->warehouseId];
            $netDelta[$key] = ($netDelta[$key] ?? 0) + $change->delta;
        }

        $locked = $this->stock->lockQuantities(array_values($keys));

        foreach ($netDelta as $key => $delta) {
            $available = $locked[$key] ?? 0;
            if ($available + $delta < 0) {
                throw new BusinessRuleException(sprintf(
                    'Stok %s tidak mencukupi: tersedia %d, dibutuhkan %d.',
                    $keys[$key]['sku'],
                    $available,
                    -$delta,
                ));
            }
        }

        try {
            foreach ($changes as $change) {
                $this->stock->recordMovement($change);
            }
        } catch (InsufficientStockException $e) {
            // Lapis kedua (guard SQL) menolak walaupun cek di atas lolos — mis. ada
            // jalur kode yang mengubah stok tanpa lock. Transaksi pemanggil di-rollback.
            throw new BusinessRuleException('Stok ' . $e->sku . ' tidak mencukupi. Silakan muat ulang dan coba lagi.', 0, $e);
        }
    }
}
