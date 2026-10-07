<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DateRange;
use App\Entity\LedgerEntry;
use App\Entity\MovementSummary;
use App\Entity\OrderSummary;
use App\Entity\StatusCount;

/**
 * Query agregasi yang dipakai BERSAMA oleh dashboard (DASH-01) dan laporan
 * CSV (REPORT-01), sehingga angka di layar dan di file selalu sama.
 */
interface ReportRepositoryInterface
{
    /**
     * Nilai inventori = SUM(stok seluruh gudang x harga beli), produk aktif.
     */
    public function inventoryValue(): int;

    /**
     * Jumlah & nilai order per status.
     *
     * @param 'PO'|'SO' $orderType
     * @param DateRange|null $range filter tanggal order; null = semua waktu
     * @param int|null $createdBy hanya SO buatan user ini (Sales); diabaikan untuk PO
     * @return list<StatusCount>
     */
    public function orderStatusCounts(string $orderType, ?DateRange $range = null, ?int $createdBy = null): array;

    /**
     * Rekap Receipt/Issue/Adjustment per produk+gudang dari stock_ledger.
     *
     * @return list<MovementSummary>
     */
    public function stockMovementSummary(DateRange $range): array;

    /**
     * Baris stock_ledger dalam rentang, urut waktu.
     *
     * @return list<LedgerEntry>
     */
    public function ledgerEntries(DateRange $range): array;

    /**
     * Antrean goods receipt: PO berstatus Ordered / PartiallyReceived, terlama dulu.
     *
     * @return list<OrderSummary>
     */
    public function receiptQueue(int $limit): array;

    /**
     * Antrean goods issue: SO berstatus Approved, terlama dulu.
     *
     * @return list<OrderSummary>
     */
    public function issueQueue(int $limit): array;
}
