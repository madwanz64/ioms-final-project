<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewOrderLine;
use App\Entity\OrderSummary;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderStatus;

interface SalesOrderRepositoryInterface
{
    /**
     * Menghormati $criteria->createdBy dan $criteria->hiddenStatuses.
     *
     * @return PaginatedResult<OrderSummary>
     */
    public function search(OrderSearchCriteria $criteria): PaginatedResult;

    public function findById(int $id): ?SalesOrder;

    /**
     * Seperti findById, dengan SELECT ... FOR UPDATE pada order & item-nya.
     * Wajib di dalam transaksi.
     */
    public function lockById(int $id): ?SalesOrder;

    /**
     * Simpan SO berstatus Draft; nomor SO dibuat otomatis.
     *
     * @param list<NewOrderLine> $lines
     * @return int id SO baru
     */
    public function create(int $customerId, int $warehouseId, int $createdBy, string $orderDate, array $lines): int;

    /**
     * @param int|null $approvedBy diisi hanya saat order disetujui
     */
    public function updateStatus(int $id, SalesOrderStatus $status, ?int $approvedBy = null): void;
}
