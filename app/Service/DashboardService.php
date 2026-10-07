<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DateRange;
use App\Entity\MovementSummary;
use App\Entity\OrderSummary;
use App\Entity\ProductSummary;
use App\Entity\StatusCount;
use App\Entity\User;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductSearchCriteria;
use App\Repository\ReportRepositoryInterface;
use DateTimeImmutable;

/**
 * Ringkasan dashboard per role (DASH-01). Semua angka berasal dari query
 * agregasi yang sama dengan laporan CSV (ReportRepositoryInterface).
 */
final class DashboardService
{
    private const LOW_STOCK_LIMIT = 8;
    private const QUEUE_LIMIT = 5;
    private const MOVEMENT_DAYS = 90;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ReportRepositoryInterface $reports,
        private readonly ReportService $reportService,
        private readonly DateTimeImmutable $today,
    ) {
    }

    /**
     * @return array{activeProducts: int, lowStockCount: int, lowStock: list<ProductSummary>}
     */
    public function stockSummary(): array
    {
        $active = $this->products->search((new ProductSearchCriteria(perPage: 1))->withOnlyActive());
        $low = $this->products->search(
            (new ProductSearchCriteria(stockStatus: 'low', sort: 'stock_asc', perPage: self::LOW_STOCK_LIMIT))->withOnlyActive()
        );

        return [
            'activeProducts' => $active->total,
            'lowStockCount' => $low->total,
            'lowStock' => $low->items,
        ];
    }

    /**
     * Admin: nilai inventori, produk di bawah reorder point, order per status (PO & SO).
     *
     * @return array{inventoryValue: int, stock: array{activeProducts: int, lowStockCount: int, lowStock: list<ProductSummary>}, statusCounts: list<StatusCount>, movement: array{receipt: int, issue: int, days: int}}
     */
    public function forAdmin(User $admin): array
    {
        return [
            'inventoryValue' => $this->reports->inventoryValue(),
            'stock' => $this->stockSummary(),
            'statusCounts' => $this->reportService->statusCounts($admin, null),
            'movement' => $this->movementTotals(),
        ];
    }

    /**
     * Sales: ringkasan order miliknya per status.
     *
     * @return array{statusCounts: list<StatusCount>}
     */
    public function forSales(User $sales): array
    {
        return ['statusCounts' => $this->reportService->statusCounts($sales, null)];
    }

    /**
     * Warehouse Staff: antrean goods receipt / goods issue dan produk low-stock.
     *
     * @return array{stock: array{activeProducts: int, lowStockCount: int, lowStock: list<ProductSummary>}, receiptQueue: list<OrderSummary>, issueQueue: list<OrderSummary>, receiptPending: int, issuePending: int, movement: array{receipt: int, issue: int, days: int}}
     */
    public function forWarehouse(): array
    {
        $poCounts = $this->reports->orderStatusCounts('PO');
        $soCounts = $this->reports->orderStatusCounts('SO');

        return [
            'stock' => $this->stockSummary(),
            'receiptQueue' => $this->reports->receiptQueue(self::QUEUE_LIMIT),
            'issueQueue' => $this->reports->issueQueue(self::QUEUE_LIMIT),
            'receiptPending' => self::countFor($poCounts, ['Ordered', 'PartiallyReceived']),
            'issuePending' => self::countFor($soCounts, ['Approved']),
            'movement' => $this->movementTotals(),
        ];
    }

    /**
     * @param list<StatusCount> $counts
     * @param list<string> $statuses
     */
    public static function countFor(array $counts, array $statuses): int
    {
        return array_sum(array_map(
            static fn (StatusCount $c): int => in_array($c->status, $statuses, true) ? $c->count : 0,
            $counts,
        ));
    }

    /**
     * Total unit masuk/keluar N hari terakhir — dari rekap yang sama dengan CSV rekap stok.
     *
     * @return array{receipt: int, issue: int, days: int}
     */
    private function movementTotals(): array
    {
        $range = new DateRange($this->today->modify('-' . (self::MOVEMENT_DAYS - 1) . ' days'), $this->today);
        $rows = $this->reports->stockMovementSummary($range);

        return [
            'receipt' => array_sum(array_map(static fn (MovementSummary $m): int => $m->receipt, $rows)),
            'issue' => -array_sum(array_map(static fn (MovementSummary $m): int => $m->issue, $rows)),
            'days' => self::MOVEMENT_DAYS,
        ];
    }
}
