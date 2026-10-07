<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseOrderStatus;
use PDO;
use RuntimeException;

final class MySqlPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /** Peta whitelist ORDER BY (nilai sort dari user hanya memilih kunci). */
    private const ORDER_BY = [
        'date_desc' => 'po.order_date DESC, po.id DESC',
        'date_asc' => 'po.order_date ASC, po.id ASC',
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
            $where[] = '(po.order_no LIKE :q1 OR s.name LIKE :q2)';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if ($criteria->status !== '') {
            $where[] = 'po.status = :status';
            $params['status'] = $criteria->status;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $from = ' FROM purchase_orders po
                  JOIN suppliers s ON s.id = po.supplier_id
                  JOIN warehouses w ON w.id = po.warehouse_id';

        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = min($criteria->page, max(1, (int) ceil($total / $criteria->perPage)));

        $stmt = $this->pdo->prepare(
            'SELECT po.id, po.order_no, s.name AS party_name, w.name AS warehouse_name, po.status, po.order_date,
                    (SELECT COUNT(*) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS item_count,
                    (SELECT COALESCE(SUM(i.qty * i.buy_price), 0) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS total'
            . $from . $whereSql
            . ' ORDER BY ' . self::ORDER_BY[$criteria->sort] . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $criteria->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $criteria->perPage, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map([RowMapper::class, 'orderSummary'], $stmt->fetchAll());

        return new PaginatedResult(array_values($items), $total, $page, $criteria->perPage);
    }

    public function findById(int $id): ?PurchaseOrder
    {
        return $this->load($id, false);
    }

    public function lockById(int $id): ?PurchaseOrder
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('lockById() harus dipanggil di dalam transaksi.');
        }

        return $this->load($id, true);
    }

    public function create(int $supplierId, int $warehouseId, string $orderDate, array $lines): int
    {
        return Database::transactional($this->pdo, function () use ($supplierId, $warehouseId, $orderDate, $lines): int {
            // Nomor sementara yang unik, lalu diganti nomor final berbasis id
            // auto-increment (PO-2026-0013) — id dijamin unik oleh MySQL, sehingga
            // dua PO yang dibuat bersamaan tidak bisa mendapat nomor yang sama.
            $header = $this->pdo->prepare(
                "INSERT INTO purchase_orders (order_no, supplier_id, warehouse_id, status, order_date)
                 VALUES (:order_no, :supplier_id, :warehouse_id, 'Draft', :order_date)"
            );
            $header->execute([
                'order_no' => 'TMP-' . bin2hex(random_bytes(8)),
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'order_date' => $orderDate,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $number = $this->pdo->prepare("UPDATE purchase_orders SET order_no = CONCAT('PO-', YEAR(order_date), '-', LPAD(id, 4, '0')) WHERE id = :id");
            $number->execute(['id' => $id]);

            $item = $this->pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, product_sku, qty, received_qty, buy_price)
                 VALUES (:order_id, :sku, :qty, 0, :buy_price)'
            );
            foreach ($lines as $line) {
                $item->execute(['order_id' => $id, 'sku' => $line->sku, 'qty' => $line->qty, 'buy_price' => $line->price]);
            }

            return $id;
        });
    }

    public function updateStatus(int $id, PurchaseOrderStatus $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE purchase_orders SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status->value]);
    }

    public function addReceivedQty(int $itemId, int $qty): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE purchase_order_items SET received_qty = received_qty + :qty
             WHERE id = :id AND received_qty + :qty_check <= qty'
        );
        $stmt->execute(['id' => $itemId, 'qty' => $qty, 'qty_check' => $qty]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(sprintf('Penerimaan item #%d melebihi qty pesanan.', $itemId));
        }
    }

    private function load(int $id, bool $forUpdate): ?PurchaseOrder
    {
        // OF <alias>: hanya baris order yang dikunci, bukan supplier/gudang/produk yang ikut di-JOIN.
        $stmt = $this->pdo->prepare(
            'SELECT po.id, po.order_no, po.supplier_id, s.name AS supplier_name, po.warehouse_id, w.name AS warehouse_name,
                    po.status, po.order_date
             FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             JOIN warehouses w ON w.id = po.warehouse_id
             WHERE po.id = :id' . ($forUpdate ? ' FOR UPDATE OF po' : '')
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT i.id, i.product_sku, p.name, p.unit, i.qty, i.received_qty, i.buy_price
             FROM purchase_order_items i
             JOIN products p ON p.sku = i.product_sku
             WHERE i.purchase_order_id = :id
             ORDER BY i.id' . ($forUpdate ? ' FOR UPDATE OF i' : '')
        );
        $itemsStmt->execute(['id' => $id]);
        $items = array_map(static fn (array $item): PurchaseOrderItem => new PurchaseOrderItem(
            (int) $item['id'],
            (string) $item['product_sku'],
            (string) $item['name'],
            (string) $item['unit'],
            (int) $item['qty'],
            (int) $item['received_qty'],
            RowMapper::money($item['buy_price']),
        ), $itemsStmt->fetchAll());

        return new PurchaseOrder(
            (int) $row['id'],
            (string) $row['order_no'],
            (int) $row['supplier_id'],
            (string) $row['supplier_name'],
            (int) $row['warehouse_id'],
            (string) $row['warehouse_name'],
            PurchaseOrderStatus::from((string) $row['status']),
            (string) $row['order_date'],
            array_values($items),
        );
    }
}
