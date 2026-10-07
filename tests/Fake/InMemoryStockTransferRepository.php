<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\StockTransfer;
use App\Entity\StockTransferItem;
use App\Repository\OrderSearchCriteria;
use App\Repository\PaginatedResult;
use App\Repository\StockTransferRepositoryInterface;

final class InMemoryStockTransferRepository implements StockTransferRepositoryInterface
{
    /** @var array<int, StockTransfer> */
    private array $transfers = [];

    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        return new PaginatedResult([], count($this->transfers), 1, $criteria->perPage);
    }

    public function findById(int $id): ?StockTransfer
    {
        return $this->transfers[$id] ?? null;
    }

    public function create(int $fromWarehouseId, int $toWarehouseId, int $createdBy, ?string $note, array $lines): int
    {
        $id = count($this->transfers) + 1;
        $this->transfers[$id] = new StockTransfer(
            $id,
            sprintf('TRF-TEST-%04d', $id),
            $fromWarehouseId,
            'Gudang ' . $fromWarehouseId,
            $toWarehouseId,
            'Gudang ' . $toWarehouseId,
            $note,
            $createdBy,
            'Pembuat',
            '2026-10-07 10:00:00',
            array_map(static fn ($line): StockTransferItem => new StockTransferItem($line->sku, $line->sku, 'pcs', $line->qty), $lines),
        );

        return $id;
    }
}
