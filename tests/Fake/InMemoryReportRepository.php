<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\DateRange;
use App\Entity\StatusCount;
use App\Repository\ReportRepositoryInterface;

/**
 * Fake repository agregasi: mengembalikan data yang disiapkan test dan
 * mencatat argumen pemanggilan (mis. untuk memastikan Sales dibatasi created_by).
 */
final class InMemoryReportRepository implements ReportRepositoryInterface
{
    public int $inventoryValue = 0;

    /** @var array<string, list<StatusCount>> per jenis order */
    public array $statusCounts = ['PO' => [], 'SO' => []];

    /** @var list<\App\Entity\MovementSummary> */
    public array $movements = [];

    /** @var list<\App\Entity\LedgerEntry> */
    public array $ledger = [];

    /** @var list<array{type: string, range: DateRange|null, createdBy: int|null}> */
    public array $statusCalls = [];

    public function inventoryValue(): int
    {
        return $this->inventoryValue;
    }

    public function orderStatusCounts(string $orderType, ?DateRange $range = null, ?int $createdBy = null): array
    {
        $this->statusCalls[] = ['type' => $orderType, 'range' => $range, 'createdBy' => $createdBy];

        return $this->statusCounts[$orderType] ?? [];
    }

    public function stockMovementSummary(DateRange $range): array
    {
        return $this->movements;
    }

    public function ledgerEntries(DateRange $range): array
    {
        return $this->ledger;
    }

    public function receiptQueue(int $limit): array
    {
        return [];
    }

    public function issueQueue(int $limit): array
    {
        return [];
    }
}
