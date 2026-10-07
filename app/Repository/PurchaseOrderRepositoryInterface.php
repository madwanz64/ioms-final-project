<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewOrderLine;
use App\Entity\OrderSummary;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderStatus;

interface PurchaseOrderRepositoryInterface
{
    /**
     * @return PaginatedResult<OrderSummary>
     */
    public function search(OrderSearchCriteria $criteria): PaginatedResult;

    public function findById(int $id): ?PurchaseOrder;

    /**
     * Sama dengan findById, tetapi baris PO beserta item-nya dikunci
     * (SELECT ... FOR UPDATE). Wajib dipanggil di dalam transaksi.
     */
    public function lockById(int $id): ?PurchaseOrder;

    /**
     * Simpan PO berstatus Draft beserta item-nya; nomor PO dibuat otomatis.
     *
     * @param list<NewOrderLine> $lines
     * @return int id PO baru
     */
    public function create(int $supplierId, int $warehouseId, string $orderDate, array $lines): int;

    public function updateStatus(int $id, PurchaseOrderStatus $status): void;

    /**
     * Tambah received_qty satu item. Ditolak (exception) bila melebihi qty pesanan.
     */
    public function addReceivedQty(int $itemId, int $qty): void;
}
