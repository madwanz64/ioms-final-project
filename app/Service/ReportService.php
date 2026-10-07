<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DateRange;
use App\Entity\LedgerEntry;
use App\Entity\MovementSummary;
use App\Entity\PurchaseOrderStatus;
use App\Entity\Role;
use App\Entity\SalesOrderStatus;
use App\Entity\StatusCount;
use App\Entity\User;
use App\Repository\ReportRepositoryInterface;
use DateTimeImmutable;

/**
 * Laporan & ekspor CSV (REPORT-01). Hak unduh mengikuti §1.2:
 * Admin = semua laporan; Sales = status order miliknya; Warehouse Staff = laporan stok.
 */
final class ReportService
{
    public const STOCK_LEDGER = 'stock-ledger';
    public const STOCK_SUMMARY = 'stock-summary';
    public const ORDER_STATUS = 'order-status';

    public const MAX_DAYS = 366;
    private const DEFAULT_DAYS = 90;

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly DateTimeImmutable $today,
    ) {
    }

    /**
     * @return list<string> jenis laporan yang boleh dibuka/diunduh user ini
     */
    public function availableReports(User $user): array
    {
        return match ($user->role) {
            Role::Admin => [self::STOCK_LEDGER, self::STOCK_SUMMARY, self::ORDER_STATUS],
            Role::WarehouseStaff => [self::STOCK_LEDGER, self::STOCK_SUMMARY],
            Role::Sales => [self::ORDER_STATUS],
        };
    }

    /**
     * Rentang dari query string ?from=YYYY-MM-DD&to=YYYY-MM-DD. Kosong = 90 hari terakhir.
     *
     * @param array<string, string> $query
     * @throws ValidationException
     */
    public function range(array $query): DateRange
    {
        if (($query['from'] ?? '') === '' && ($query['to'] ?? '') === '') {
            return new DateRange($this->today->modify('-' . (self::DEFAULT_DAYS - 1) . ' days'), $this->today);
        }

        $validator = new InputValidator($query);
        $from = $validator->dateNotAfter('from', 'Tanggal awal', $this->today);
        $to = $validator->dateNotAfter('to', 'Tanggal akhir', $this->today);
        $validator->throwIfInvalid();

        $fromDate = new DateTimeImmutable($from);
        $toDate = new DateTimeImmutable($to);
        if ($fromDate > $toDate) {
            throw new ValidationException(['from' => 'Tanggal awal tidak boleh setelah tanggal akhir.']);
        }
        $range = new DateRange($fromDate, $toDate);
        if ($range->days() > self::MAX_DAYS) {
            throw new ValidationException(['from' => sprintf('Rentang maksimal %d hari.', self::MAX_DAYS)]);
        }

        return $range;
    }

    /**
     * Data untuk tampilan halaman laporan (sama persis dengan isi CSV).
     *
     * @return array{statusCounts: list<StatusCount>, movements: list<MovementSummary>, ledger: list<LedgerEntry>}
     */
    public function preview(User $user, DateRange $range): array
    {
        $allowed = $this->availableReports($user);

        return [
            'statusCounts' => in_array(self::ORDER_STATUS, $allowed, true) ? $this->statusCounts($user, $range) : [],
            'movements' => in_array(self::STOCK_SUMMARY, $allowed, true) ? $this->reports->stockMovementSummary($range) : [],
            'ledger' => in_array(self::STOCK_LEDGER, $allowed, true) ? $this->reports->ledgerEntries($range) : [],
        ];
    }

    /**
     * @throws AuthorizationException laporan tidak boleh diunduh user ini
     * @throws NotFoundException jenis laporan tidak dikenal
     */
    public function export(string $type, User $user, DateRange $range): CsvExport
    {
        if (!in_array($type, [self::STOCK_LEDGER, self::STOCK_SUMMARY, self::ORDER_STATUS], true)) {
            throw new NotFoundException('Jenis laporan tidak dikenal.');
        }
        if (!in_array($type, $this->availableReports($user), true)) {
            throw new AuthorizationException('Anda tidak berwenang mengunduh laporan ini.');
        }

        $suffix = $range->fromDate() . '_' . $range->toDate() . '.csv';

        return match ($type) {
            self::STOCK_LEDGER => new CsvExport('pergerakan-stok_' . $suffix, $this->ledgerRows($range)),
            self::STOCK_SUMMARY => new CsvExport('rekap-stok_' . $suffix, $this->summaryRows($range)),
            default => new CsvExport('status-order_' . $suffix, $this->statusRows($user, $range)),
        };
    }

    /**
     * Admin: PO + SO seluruhnya. Sales: hanya SO buatannya. Status tanpa order tetap
     * muncul dengan nilai 0 agar rekap lengkap.
     *
     * @return list<StatusCount>
     */
    public function statusCounts(User $user, ?DateRange $range): array
    {
        $result = [];
        if ($user->role === Role::Admin) {
            $result = self::withAllStatuses('PO', $this->reports->orderStatusCounts('PO', $range), array_map(
                static fn (PurchaseOrderStatus $s): string => $s->value,
                PurchaseOrderStatus::cases(),
            ));
        }
        $createdBy = $user->role === Role::Sales ? $user->id : null;

        return array_merge($result, self::withAllStatuses('SO', $this->reports->orderStatusCounts('SO', $range, $createdBy), array_map(
            static fn (SalesOrderStatus $s): string => $s->value,
            SalesOrderStatus::cases(),
        )));
    }

    /**
     * @param list<StatusCount> $counts
     * @param list<string> $statuses
     * @return list<StatusCount>
     */
    public static function withAllStatuses(string $orderType, array $counts, array $statuses): array
    {
        $byStatus = [];
        foreach ($counts as $count) {
            $byStatus[$count->status] = $count;
        }

        return array_map(
            static fn (string $status): StatusCount => $byStatus[$status] ?? new StatusCount($orderType, $status, 0, 0),
            $statuses,
        );
    }

    /**
     * @return list<list<string|int>>
     */
    private function ledgerRows(DateRange $range): array
    {
        $rows = [['Waktu', 'SKU', 'Produk', 'Gudang', 'Tipe', 'Qty', 'Ref Tipe', 'Ref No', 'Dilakukan Oleh']];
        foreach ($this->reports->ledgerEntries($range) as $e) {
            $rows[] = [$e->createdAt, $e->sku, $e->productName, $e->warehouseName, $e->movementType, $e->quantity, $e->refType, $e->refId, $e->performedBy];
        }

        return $rows;
    }

    /**
     * @return list<list<string|int>>
     */
    private function summaryRows(DateRange $range): array
    {
        $rows = [['SKU', 'Produk', 'Gudang', 'Receipt', 'Issue', 'Adjustment', 'Netto']];
        foreach ($this->reports->stockMovementSummary($range) as $m) {
            $rows[] = [$m->sku, $m->productName, $m->warehouseName, $m->receipt, $m->issue, $m->adjustment, $m->net()];
        }

        return $rows;
    }

    /**
     * @return list<list<string|int>>
     */
    private function statusRows(User $user, DateRange $range): array
    {
        $rows = [['Jenis', 'Status', 'Jumlah Order', 'Nilai (Rp)']];
        foreach ($this->statusCounts($user, $range) as $c) {
            $rows[] = [$c->orderType, $c->status, $c->count, $c->total];
        }

        return $rows;
    }
}
