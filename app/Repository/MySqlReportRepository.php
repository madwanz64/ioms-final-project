<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DateRange;
use App\Entity\LedgerEntry;
use App\Entity\MovementSummary;
use App\Entity\OrderSummary;
use App\Entity\StatusCount;
use PDO;

final class MySqlReportRepository implements ReportRepositoryInterface
{
    /**
     * Tabel & kolom per jenis order. Dipilih dari peta tetap ini (bukan dari input user),
     * sehingga aman disisipkan ke SQL.
     */
    private const ORDER_TABLES = [
        'PO' => ['orders' => 'purchase_orders', 'items' => 'purchase_order_items', 'fk' => 'purchase_order_id', 'price' => 'buy_price'],
        'SO' => ['orders' => 'sales_orders', 'items' => 'sales_order_items', 'fk' => 'sales_order_id', 'price' => 'price'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function inventoryValue(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COALESCE(SUM(ps.quantity * p.buy_price), 0)
             FROM product_stock ps
             JOIN products p ON p.sku = ps.product_sku
             WHERE p.active = 1'
        );

        return $stmt === false ? 0 : RowMapper::money($stmt->fetchColumn());
    }

    public function orderStatusCounts(string $orderType, ?DateRange $range = null, ?int $createdBy = null): array
    {
        $t = self::ORDER_TABLES[$orderType];

        $where = [];
        $params = [];
        if ($range !== null) {
            $where[] = 'o.order_date BETWEEN :from AND :to';
            $params['from'] = $range->fromDate();
            $params['to'] = $range->toDate();
        }
        if ($createdBy !== null && $orderType === 'SO') {
            $where[] = 'o.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }

        $stmt = $this->pdo->prepare(
            'SELECT o.status, COUNT(*) AS order_count, COALESCE(SUM(t.total), 0) AS total
             FROM ' . $t['orders'] . ' o
             LEFT JOIN (
                 SELECT ' . $t['fk'] . ' AS order_id, SUM(qty * ' . $t['price'] . ') AS total
                 FROM ' . $t['items'] . '
                 GROUP BY ' . $t['fk'] . '
             ) t ON t.order_id = o.id'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' GROUP BY o.status'
        );
        $stmt->execute($params);

        return array_values(array_map(static fn (array $row): StatusCount => new StatusCount(
            $orderType,
            (string) $row['status'],
            (int) $row['order_count'],
            RowMapper::money($row['total']),
        ), $stmt->fetchAll()));
    }

    public function stockMovementSummary(DateRange $range): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.product_sku, p.name AS product_name, w.name AS warehouse_name,
                    SUM(CASE WHEN l.movement_type = 'Receipt' THEN l.quantity ELSE 0 END) AS receipt,
                    SUM(CASE WHEN l.movement_type = 'Issue' THEN l.quantity ELSE 0 END) AS issue,
                    SUM(CASE WHEN l.movement_type = 'Adjustment' THEN l.quantity ELSE 0 END) AS adjustment
             FROM stock_ledger l
             JOIN products p ON p.sku = l.product_sku
             JOIN warehouses w ON w.id = l.warehouse_id
             WHERE l.created_at >= :from AND l.created_at < :to
             GROUP BY l.product_sku, p.name, w.name
             ORDER BY p.name, w.name"
        );
        $stmt->execute(['from' => $range->fromDate() . ' 00:00:00', 'to' => $range->toExclusiveDateTime()]);

        return array_values(array_map(static fn (array $row): MovementSummary => new MovementSummary(
            (string) $row['product_sku'],
            (string) $row['product_name'],
            (string) $row['warehouse_name'],
            (int) $row['receipt'],
            (int) $row['issue'],
            (int) $row['adjustment'],
        ), $stmt->fetchAll()));
    }

    public function ledgerEntries(DateRange $range): array
    {
        // idx_ledger_created_at dipakai untuk filter rentang ini (DB-01).
        $stmt = $this->pdo->prepare(
            'SELECT l.created_at, l.product_sku, p.name AS product_name, w.name AS warehouse_name,
                    l.movement_type, l.quantity, l.ref_type, l.ref_id, u.name AS performed_by
             FROM stock_ledger l
             JOIN products p ON p.sku = l.product_sku
             JOIN warehouses w ON w.id = l.warehouse_id
             JOIN users u ON u.id = l.performed_by
             WHERE l.created_at >= :from AND l.created_at < :to
             ORDER BY l.created_at, l.id'
        );
        $stmt->execute(['from' => $range->fromDate() . ' 00:00:00', 'to' => $range->toExclusiveDateTime()]);

        return array_values(array_map(static fn (array $row): LedgerEntry => new LedgerEntry(
            (string) $row['created_at'],
            (string) $row['product_sku'],
            (string) $row['product_name'],
            (string) $row['warehouse_name'],
            (string) $row['movement_type'],
            (int) $row['quantity'],
            (string) $row['ref_type'],
            (string) $row['ref_id'],
            (string) $row['performed_by'],
        ), $stmt->fetchAll()));
    }

    public function receiptQueue(int $limit): array
    {
        return $this->queue('PO', "o.status IN ('Ordered', 'PartiallyReceived')", 'suppliers', 'supplier_id', $limit);
    }

    public function issueQueue(int $limit): array
    {
        return $this->queue('SO', "o.status = 'Approved'", 'customers', 'customer_id', $limit);
    }

    /**
     * @param 'PO'|'SO' $orderType
     * @return list<OrderSummary>
     */
    private function queue(string $orderType, string $statusCondition, string $partyTable, string $partyColumn, int $limit): array
    {
        $t = self::ORDER_TABLES[$orderType];
        $stmt = $this->pdo->prepare(
            'SELECT o.id, o.order_no, party.name AS party_name, w.name AS warehouse_name, o.status, o.order_date,
                    (SELECT COUNT(*) FROM ' . $t['items'] . ' i WHERE i.' . $t['fk'] . ' = o.id) AS item_count,
                    (SELECT COALESCE(SUM(i.qty * i.' . $t['price'] . '), 0) FROM ' . $t['items'] . ' i WHERE i.' . $t['fk'] . ' = o.id) AS total
             FROM ' . $t['orders'] . ' o
             JOIN ' . $partyTable . ' party ON party.id = o.' . $partyColumn . '
             JOIN warehouses w ON w.id = o.warehouse_id
             WHERE ' . $statusCondition . '
             ORDER BY o.order_date ASC, o.id ASC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map([RowMapper::class, 'orderSummary'], $stmt->fetchAll()));
    }
}
