<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewOrderLine;
use App\Entity\StockTransfer;
use App\Entity\TransferSummary;

interface StockTransferRepositoryInterface
{
    /**
     * Cari nomor transfer / nama gudang, sort tanggal, 10 per halaman
     * (memakai OrderSearchCriteria; status tidak dipakai).
     *
     * @return PaginatedResult<TransferSummary>
     */
    public function search(OrderSearchCriteria $criteria): PaginatedResult;

    public function findById(int $id): ?StockTransfer;

    /**
     * Simpan dokumen transfer beserta item-nya; nomor transfer dibuat otomatis.
     * Hanya dokumen — perubahan stok dilakukan StockService dalam transaksi pemanggil.
     *
     * @param list<NewOrderLine> $lines qty per produk (price diabaikan)
     * @return int id transfer baru
     */
    public function create(int $fromWarehouseId, int $toWarehouseId, int $createdBy, ?string $note, array $lines): int;
}
