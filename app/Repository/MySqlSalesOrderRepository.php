<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\SalesOrderStatus;
use PDO;

final class MySqlSalesOrderRepository implements SalesOrderRepositoryInterface
{
    private const ORDER_BY = [
        'date_desc' => 'so.order_date DESC, so.id DESC',
        'date_asc' => 'so.order_date ASC, so.id ASC',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        $where = [];
        $params = [];
        if ($criteria->search !== '') {
            $like = '%' . addcslashes($criteria->search, '%_\\') . '%';
            $where[] = '(so.order_no LIKE :q1 OR c.name LIKE :q2)';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if ($criteria->status !== '') {
            $where[] = 'so.status = :status';
            $params['status'] = $criteria->status;
        }
        if ($criteria->createdBy !== null) {
            // Sales hanya melihat order miliknya (§1.2) — dibatasi di query, bukan di view.
            $where[] = 'so.created_by = :created_by';
            $params['created_by'] = $criteria->createdBy;
        }
        if ($criteria->hiddenStatuses !== []) {
            $placeholders = [];
            foreach ($criteria->hiddenStatuses as $i => $status) {
                $placeholders[] = ':hidden' . $i;
                $params['hidden' . $i] = $status;
            }
            $where[] = 'so.status NOT IN (' . implode(', ', $placeholders) . ')';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $from = ' FROM sales_orders so
                  JOIN customers c ON c.id = so.customer_id
                  JOIN warehouses w ON w.id = so.warehouse_id';

        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = min($criteria->page, max(1, (int) ceil($total / $criteria->perPage)));

        $stmt = $this->pdo->prepare(
            'SELECT so.id, so.order_no, c.name AS party_name, w.name AS warehouse_name, so.status, so.order_date,
                    (SELECT COUNT(*) FROM sales_order_items i WHERE i.sales_order_id = so.id) AS item_count,
                    (SELECT COALESCE(SUM(i.qty * i.price), 0) FROM sales_order_items i WHERE i.sales_order_id = so.id) AS total'
            . $from . $whereSql
            . ' ORDER BY ' . self::ORDER_BY[$criteria->sort] . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $criteria->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $criteria->perPage, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map([RowMapper::class, 'orderSummary'], $stmt->fetchAll());

        return new PaginatedResult(array_values($items), $total, $page, $criteria->perPage);
    }

    public function findById(int $id): ?SalesOrder
    {
        return $this->load($id, false);
    }

    public function lockById(int $id): ?SalesOrder
    {
        if (!$this->pdo->inTransaction()) {
            throw new PersistenceException('lockById() harus dipanggil di dalam transaksi.');
        }

        return $this->load($id, true);
    }

    public function create(int $customerId, int $warehouseId, int $createdBy, string $orderDate, array $lines): int
    {
        return Database::transactional($this->pdo, function () use ($customerId, $warehouseId, $createdBy, $orderDate, $lines): int {
            // Sama seperti PO: nomor final berbasis id auto-increment agar selalu unik.
            $header = $this->pdo->prepare(
                "INSERT INTO sales_orders (order_no, customer_id, warehouse_id, created_by, status, order_date)
                 VALUES (:order_no, :customer_id, :warehouse_id, :created_by, 'Draft', :order_date)"
            );
            $header->execute([
                'order_no' => 'TMP-' . bin2hex(random_bytes(8)),
                'customer_id' => $customerId,
                'warehouse_id' => $warehouseId,
                'created_by' => $createdBy,
                'order_date' => $orderDate,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $number = $this->pdo->prepare("UPDATE sales_orders SET order_no = CONCAT('SO-', YEAR(order_date), '-', LPAD(id, 4, '0')) WHERE id = :id");
            $number->execute(['id' => $id]);

            $item = $this->pdo->prepare(
                'INSERT INTO sales_order_items (sales_order_id, product_sku, qty, price) VALUES (:order_id, :sku, :qty, :price)'
            );
            foreach ($lines as $line) {
                $item->execute(['order_id' => $id, 'sku' => $line->sku, 'qty' => $line->qty, 'price' => $line->price]);
            }

            return $id;
        });
    }

    public function updateStatus(int $id, SalesOrderStatus $status, ?int $approvedBy = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sales_orders SET status = :status, approved_by = COALESCE(:approved_by, approved_by) WHERE id = :id'
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->bindValue('status', $status->value);
        $stmt->bindValue('approved_by', $approvedBy, $approvedBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
    }

    private function load(int $id, bool $forUpdate): ?SalesOrder
    {
        // OF <alias>: hanya baris order yang dikunci, bukan customer/gudang/user yang ikut di-JOIN.
        $stmt = $this->pdo->prepare(
            'SELECT so.id, so.order_no, so.customer_id, c.name AS customer_name, so.warehouse_id, w.name AS warehouse_name,
                    so.created_by, creator.name AS creator_name, so.approved_by, approver.name AS approver_name,
                    so.status, so.order_date
             FROM sales_orders so
             JOIN customers c ON c.id = so.customer_id
             JOIN warehouses w ON w.id = so.warehouse_id
             JOIN users creator ON creator.id = so.created_by
             LEFT JOIN users approver ON approver.id = so.approved_by
             WHERE so.id = :id' . ($forUpdate ? ' FOR UPDATE OF so' : '')
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT i.id, i.product_sku, p.name, p.unit, i.qty, i.price
             FROM sales_order_items i
             JOIN products p ON p.sku = i.product_sku
             WHERE i.sales_order_id = :id
             ORDER BY i.id' . ($forUpdate ? ' FOR UPDATE OF i' : '')
        );
        $itemsStmt->execute(['id' => $id]);
        $items = array_map(static fn (array $item): SalesOrderItem => new SalesOrderItem(
            (int) $item['id'],
            (string) $item['product_sku'],
            (string) $item['name'],
            (string) $item['unit'],
            (int) $item['qty'],
            RowMapper::money($item['price']),
        ), $itemsStmt->fetchAll());

        return new SalesOrder(
            (int) $row['id'],
            (string) $row['order_no'],
            (int) $row['customer_id'],
            (string) $row['customer_name'],
            (int) $row['warehouse_id'],
            (string) $row['warehouse_name'],
            (int) $row['created_by'],
            (string) $row['creator_name'],
            $row['approved_by'] === null ? null : (int) $row['approved_by'],
            $row['approver_name'] === null ? null : (string) $row['approver_name'],
            SalesOrderStatus::from((string) $row['status']),
            (string) $row['order_date'],
            array_values($items),
        );
    }
}
