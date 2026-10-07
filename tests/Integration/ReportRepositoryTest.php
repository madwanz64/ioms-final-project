<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\DateRange;
use App\Entity\StatusCount;
use App\Repository\MySqlReportRepository;
use DateTimeImmutable;

/**
 * Query agregasi dashboard/laporan dibandingkan dengan query SQL independen.
 */
final class ReportRepositoryTest extends IntegrationTestCase
{
    private MySqlReportRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MySqlReportRepository($this->pdo);
    }

    public function testInventoryValueIsStockTimesBuyPriceOfActiveProducts(): void
    {
        $expected = $this->scalar('SELECT ROUND(SUM(ps.quantity * p.buy_price)) FROM product_stock ps JOIN products p ON p.sku = ps.product_sku WHERE p.active = 1');

        self::assertSame($expected, $this->repo->inventoryValue());
        self::assertGreaterThan(0, $expected);
    }

    public function testOrderStatusCountsRespectDateRangeAndCreator(): void
    {
        $august = $this->range('2026-08-01', '2026-08-31');

        $all = $this->countsByStatus($this->repo->orderStatusCounts('SO', $august));
        $sinta = $this->countsByStatus($this->repo->orderStatusCounts('SO', $august, 2));

        self::assertSame($this->scalar("SELECT COUNT(*) FROM sales_orders WHERE order_date BETWEEN '2026-08-01' AND '2026-08-31'"), array_sum($all));
        self::assertSame($this->scalar("SELECT COUNT(*) FROM sales_orders WHERE created_by = 2 AND order_date BETWEEN '2026-08-01' AND '2026-08-31'"), array_sum($sinta));
        self::assertLessThan(array_sum($all), array_sum($sinta));
    }

    public function testOrderTotalsAreSumOfItemQtyTimesPrice(): void
    {
        $fulfilled = array_values(array_filter($this->repo->orderStatusCounts('SO'), static fn (StatusCount $c): bool => $c->status === 'Fulfilled'))[0];

        self::assertSame(
            $this->scalar("SELECT ROUND(SUM(i.qty * i.price)) FROM sales_order_items i JOIN sales_orders o ON o.id = i.sales_order_id WHERE o.status = 'Fulfilled'"),
            $fulfilled->total,
        );
    }

    public function testStockSummaryNetMatchesLedgerAndRangeIsInclusive(): void
    {
        // Hanya 1 Agustus: baris saldo awal (08:00) harus ikut, karena tanggal akhir inklusif.
        $firstDay = $this->repo->stockMovementSummary($this->range('2026-08-01', '2026-08-01'));
        $ledgerFirstDay = $this->scalar("SELECT COALESCE(SUM(quantity), 0) FROM stock_ledger WHERE created_at >= '2026-08-01' AND created_at < '2026-08-02'");

        self::assertNotSame([], $firstDay);
        self::assertSame($ledgerFirstDay, array_sum(array_map(static fn ($m): int => $m->net(), $firstDay)));

        $wholePeriod = $this->repo->stockMovementSummary($this->range('2026-08-01', '2026-10-07'));
        self::assertSame(
            $this->scalar('SELECT SUM(quantity) FROM product_stock'),
            array_sum(array_map(static fn ($m): int => $m->net(), $wholePeriod)),
            'Seluruh ledger dijumlahkan = seluruh stok (ledger konsisten dengan product_stock)',
        );
    }

    public function testLedgerEntriesAndQueues(): void
    {
        $entries = $this->repo->ledgerEntries($this->range('2026-08-01', '2026-08-31'));
        self::assertCount($this->scalar("SELECT COUNT(*) FROM stock_ledger WHERE created_at >= '2026-08-01' AND created_at < '2026-09-01'"), $entries);
        self::assertSame('Budi Santoso', $entries[0]->performedBy);

        $receipt = $this->repo->receiptQueue(50);
        $issue = $this->repo->issueQueue(50);
        self::assertCount($this->scalar("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Ordered', 'PartiallyReceived')"), $receipt);
        self::assertSame(['Approved'], array_values(array_unique(array_map(static fn ($o): string => $o->status, $issue))));
    }

    /**
     * @param list<StatusCount> $counts
     * @return array<string, int>
     */
    private function countsByStatus(array $counts): array
    {
        $result = [];
        foreach ($counts as $count) {
            $result[$count->status] = $count->count;
        }

        return $result;
    }

    private function range(string $from, string $to): DateRange
    {
        return new DateRange(new DateTimeImmutable($from), new DateTimeImmutable($to));
    }

    private function scalar(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }
}
