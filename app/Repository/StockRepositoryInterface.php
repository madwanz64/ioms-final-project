<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockChange;
use App\Entity\StockMovement;

/**
 * Satu-satunya jalan untuk mengubah product_stock (§1.3, ADR-001).
 */
interface StockRepositoryInterface
{
    /**
     * Kunci baris stok (SELECT ... FOR UPDATE) untuk semua pasangan produk+gudang,
     * SELALU dalam urutan SKU lalu gudang agar tidak terjadi deadlock.
     * Wajib dipanggil di dalam transaksi.
     *
     * @param list<array{sku: string, warehouseId: int}> $keys
     * @return array<string, int> StockChange::key(sku, gudang) => quantity saat ini
     */
    public function lockQuantities(array $keys): array;

    /**
     * Tulis satu baris stock_ledger DAN ubah product_stock dalam transaksi yang
     * sedang berjalan. Update stok memakai guard `quantity + delta >= 0`; bila
     * tidak ada baris yang berubah, melempar InsufficientStockException.
     *
     * @throws InsufficientStockException
     */
    public function recordMovement(StockChange $change): void;

    /**
     * Riwayat ledger untuk satu dokumen (mis. 'PO', 'PO-2026-0005').
     *
     * @return list<StockMovement>
     */
    public function movementsByReference(string $refType, string $refId): array;
}
