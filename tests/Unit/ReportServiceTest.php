<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\MovementSummary;
use App\Entity\Role;
use App\Entity\StatusCount;
use App\Entity\User;
use App\Service\AuthorizationException;
use App\Service\NotFoundException;
use App\Service\ReportService;
use App\Service\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryReportRepository;

/**
 * Area logic: rentang tanggal laporan, hak unduh per role (§1.2), isi CSV rekap.
 */
final class ReportServiceTest extends TestCase
{
    private InMemoryReportRepository $repo;
    private ReportService $service;

    protected function setUp(): void
    {
        $this->repo = new InMemoryReportRepository();
        $this->service = new ReportService($this->repo, new DateTimeImmutable('2026-10-07'));
    }

    public function testDefaultRangeIsLastNinetyDaysIncludingToday(): void
    {
        $range = $this->service->range([]);

        self::assertSame('2026-07-10', $range->fromDate());
        self::assertSame('2026-10-07', $range->toDate());
        self::assertSame(90, $range->days());
        self::assertSame('2026-10-08 00:00:00', $range->toExclusiveDateTime());
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidRanges')]
    public function testInvalidRangesAreRejected(array $query, string $field, string $message): void
    {
        try {
            $this->service->range($query);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertSame($message, $e->errors[$field]);
        }
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidRanges(): array
    {
        return [
            'awal setelah akhir' => [['from' => '2026-09-30', 'to' => '2026-09-01'], 'from', 'Tanggal awal tidak boleh setelah tanggal akhir.'],
            'tanggal tidak ada' => [['from' => '2026-02-30', 'to' => '2026-03-01'], 'from', 'Tanggal awal tidak valid (format YYYY-MM-DD).'],
            'akhir di masa depan' => [['from' => '2026-10-01', 'to' => '2026-10-08'], 'to', 'Tanggal akhir tidak boleh di masa depan.'],
            'lebih dari 366 hari' => [['from' => '2025-10-05', 'to' => '2026-10-06'], 'from', 'Rentang maksimal 366 hari.'],
            'hanya satu diisi' => [['from' => '2026-09-01'], 'to', 'Tanggal akhir wajib diisi.'],
        ];
    }

    public function testExactly366DaysIsAllowed(): void
    {
        self::assertSame(366, $this->service->range(['from' => '2025-10-07', 'to' => '2026-10-07'])->days());
    }

    /**
     * @return array<string, array{Role, string, bool}>
     */
    public static function downloadPermissions(): array
    {
        return [
            'Admin ledger' => [Role::Admin, ReportService::STOCK_LEDGER, true],
            'Admin status order' => [Role::Admin, ReportService::ORDER_STATUS, true],
            'Warehouse ledger' => [Role::WarehouseStaff, ReportService::STOCK_LEDGER, true],
            'Warehouse rekap stok' => [Role::WarehouseStaff, ReportService::STOCK_SUMMARY, true],
            'Warehouse status order' => [Role::WarehouseStaff, ReportService::ORDER_STATUS, false],
            'Sales status order' => [Role::Sales, ReportService::ORDER_STATUS, true],
            'Sales ledger' => [Role::Sales, ReportService::STOCK_LEDGER, false],
            'Sales rekap stok' => [Role::Sales, ReportService::STOCK_SUMMARY, false],
        ];
    }

    #[DataProvider('downloadPermissions')]
    public function testDownloadPermissionsFollowRoleTable(Role $role, string $type, bool $allowed): void
    {
        $range = $this->service->range([]);
        if (!$allowed) {
            $this->expectException(AuthorizationException::class);
        }

        $csv = $this->service->export($type, $this->user($role), $range);

        self::assertStringEndsWith('_2026-07-10_2026-10-07.csv', $csv->filename);
    }

    public function testUnknownReportTypeIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->export('users', $this->user(Role::Admin), $this->service->range([]));
    }

    public function testSalesOrderStatusIsLimitedToOwnSalesOrdersAndHasNoPurchaseOrders(): void
    {
        $this->repo->statusCounts['SO'] = [new StatusCount('SO', 'Fulfilled', 3, 7_266_000)];
        $this->repo->statusCounts['PO'] = [new StatusCount('PO', 'Ordered', 9, 1)];

        $csv = $this->service->export(ReportService::ORDER_STATUS, $this->user(Role::Sales, 2), $this->service->range([]));

        self::assertSame([['type' => 'SO', 'createdBy' => 2]], array_map(
            static fn (array $call): array => ['type' => $call['type'], 'createdBy' => $call['createdBy']],
            $this->repo->statusCalls,
        ));
        self::assertNotContains('PO', array_column($csv->rows, 0));
        self::assertContains(['SO', 'Fulfilled', 3, 7_266_000], $csv->rows);
    }

    public function testStatusRecapListsEveryStatusIncludingZero(): void
    {
        $this->repo->statusCounts['SO'] = [new StatusCount('SO', 'Approved', 2, 855_000)];

        $statuses = array_map(
            static fn (StatusCount $c): string => $c->status . '=' . $c->count,
            $this->service->statusCounts($this->user(Role::Sales), null),
        );

        self::assertSame(['Draft=0', 'PendingApproval=0', 'Approved=2', 'Fulfilled=0', 'Cancelled=0'], $statuses);
    }

    public function testStockSummaryCsvHasHeaderAndNetColumn(): void
    {
        $this->repo->movements = [new MovementSummary('SKU-0001', 'Kabel', 'Gudang Jakarta', 0, -2, 5)];

        $csv = $this->service->export(ReportService::STOCK_SUMMARY, $this->user(Role::WarehouseStaff), $this->service->range([]));

        self::assertSame(['SKU', 'Produk', 'Gudang', 'Receipt', 'Issue', 'Adjustment', 'Netto'], $csv->rows[0]);
        self::assertSame(['SKU-0001', 'Kabel', 'Gudang Jakarta', 0, -2, 5, 3], $csv->rows[1]);
    }

    private function user(Role $role, int $id = 1): User
    {
        return new User($id, 'User', 'u@ioms.test', 'x', $role, true);
    }
}
