<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\StockTransfer;
use App\Entity\StockTransferItem;
use App\Entity\TransferSummary;
use PDO;

final class MySqlStockTransferRepository implements StockTransferRepositoryInterface
{
    private const ORDER_BY = [
        'date_desc' => 't.created_at DESC, t.id DESC',
        'date_asc' => 't.created_at ASC, t.id ASC',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(OrderSearchCriteria $criteria): PaginatedResult
    {
        $where = '';
        $params = [];
        if ($criteria->search !== '') {
            $like = '%' . addcslashes($criteria->search, '%_\\') . '%';
            $where = ' WHERE (t.transfer_no LIKE :q1 OR wf.name LIKE :q2 OR wt.name LIKE :q3)';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $from = ' FROM stock_transfers t
                  JOIN warehouses wf ON wf.id = t.from_warehouse_id
                  JOIN warehouses wt ON wt.id = t.to_warehouse_id
                  JOIN users u ON u.id = t.created_by';

        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = min($criteria->page, max(1, (int) ceil($total / $criteria->perPage)));

        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.transfer_no, wf.name AS from_name, wt.name AS to_name, u.name AS creator_name, t.created_at,
                    (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.stock_transfer_id = t.id) AS item_count,
                    (SELECT COALESCE(SUM(i.qty), 0) FROM stock_transfer_items i WHERE i.stock_transfer_id = t.id) AS total_qty'
            . $from . $where
            . ' ORDER BY ' . self::ORDER_BY[$criteria->sort] . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $criteria->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $criteria->perPage, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(static fn (array $row): TransferSummary => new TransferSummary(
            (int) $row['id'],
            (string) $row['transfer_no'],
            (string) $row['from_name'],
            (string) $row['to_name'],
            (string) $row['creator_name'],
            (string) $row['created_at'],
            (int) $row['item_count'],
            (int) $row['total_qty'],
        ), $stmt->fetchAll());

        return new PaginatedResult(array_values($items), $total, $page, $criteria->perPage);
    }

    public function findById(int $id): ?StockTransfer
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.transfer_no, t.from_warehouse_id, wf.name AS from_name, t.to_warehouse_id, wt.name AS to_name,
                    t.note, t.created_by, u.name AS creator_name, t.created_at
             FROM stock_transfers t
             JOIN warehouses wf ON wf.id = t.from_warehouse_id
             JOIN warehouses wt ON wt.id = t.to_warehouse_id
             JOIN users u ON u.id = t.created_by
             WHERE t.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT i.product_sku, p.name, p.unit, i.qty
             FROM stock_transfer_items i
             JOIN products p ON p.sku = i.product_sku
             WHERE i.stock_transfer_id = :id
             ORDER BY i.id'
        );
        $itemsStmt->execute(['id' => $id]);
        $items = array_map(static fn (array $item): StockTransferItem => new StockTransferItem(
            (string) $item['product_sku'],
            (string) $item['name'],
            (string) $item['unit'],
            (int) $item['qty'],
        ), $itemsStmt->fetchAll());

        return new StockTransfer(
            (int) $row['id'],
            (string) $row['transfer_no'],
            (int) $row['from_warehouse_id'],
            (string) $row['from_name'],
            (int) $row['to_warehouse_id'],
            (string) $row['to_name'],
            $row['note'] === null ? null : (string) $row['note'],
            (int) $row['created_by'],
            (string) $row['creator_name'],
            (string) $row['created_at'],
            array_values($items),
        );
    }

    public function create(int $fromWarehouseId, int $toWarehouseId, int $createdBy, ?string $note, array $lines): int
    {
        return Database::transactional($this->pdo, function () use ($fromWarehouseId, $toWarehouseId, $createdBy, $note, $lines): int {
            // Nomor final berbasis id auto-increment (TRF-2026-0001), sama seperti PO/SO.
            $header = $this->pdo->prepare(
                'INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, note, created_by)
                 VALUES (:transfer_no, :from_id, :to_id, :note, :created_by)'
            );
            $header->execute([
                'transfer_no' => 'TMP-' . bin2hex(random_bytes(8)),
                'from_id' => $fromWarehouseId,
                'to_id' => $toWarehouseId,
                'note' => $note,
                'created_by' => $createdBy,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $number = $this->pdo->prepare("UPDATE stock_transfers SET transfer_no = CONCAT('TRF-', YEAR(created_at), '-', LPAD(id, 4, '0')) WHERE id = :id");
            $number->execute(['id' => $id]);

            $item = $this->pdo->prepare('INSERT INTO stock_transfer_items (stock_transfer_id, product_sku, qty) VALUES (:id, :sku, :qty)');
            foreach ($lines as $line) {
                $item->execute(['id' => $id, 'sku' => $line->sku, 'qty' => $line->qty]);
            }

            return $id;
        });
    }
}
