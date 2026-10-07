<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockChange;
use App\Entity\StockMovement;
use PDO;
use RuntimeException;

/**
 * Implementasi ADR-001: lock baris stok berurutan + conditional update.
 */
final class MySqlStockRepository implements StockRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lockQuantities(array $keys): array
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('lockQuantities() harus dipanggil di dalam transaksi.');
        }

        // Urutan kunci tetap (SKU, lalu gudang) di semua transaksi -> mencegah deadlock.
        usort($keys, static fn (array $a, array $b): int => [$a['sku'], $a['warehouseId']] <=> [$b['sku'], $b['warehouseId']]);

        $stmt = $this->pdo->prepare(
            'SELECT quantity FROM product_stock WHERE product_sku = :sku AND warehouse_id = :warehouse_id FOR UPDATE'
        );
        $quantities = [];
        foreach ($keys as $key) {
            $stmt->execute(['sku' => $key['sku'], 'warehouse_id' => $key['warehouseId']]);
            $quantity = $stmt->fetchColumn();
            if ($quantity === false) {
                // Invarian WH-01: setiap produk punya baris stok di setiap gudang.
                throw new RuntimeException(sprintf('Baris stok %s di gudang #%d tidak ditemukan.', $key['sku'], $key['warehouseId']));
            }
            $quantities[StockChange::key($key['sku'], $key['warehouseId'])] = (int) $quantity;
        }

        return $quantities;
    }

    public function recordMovement(StockChange $change): void
    {
        $ledger = $this->pdo->prepare(
            'INSERT INTO stock_ledger (product_sku, warehouse_id, movement_type, quantity, ref_type, ref_id, performed_by)
             VALUES (:sku, :warehouse_id, :movement_type, :quantity, :ref_type, :ref_id, :performed_by)'
        );
        $ledger->execute([
            'sku' => $change->sku,
            'warehouse_id' => $change->warehouseId,
            'movement_type' => $change->movementType,
            'quantity' => $change->delta,
            'ref_type' => $change->refType,
            'ref_id' => $change->refId,
            'performed_by' => $change->performedBy,
        ]);

        // Lapis kedua ADR-001: update hanya terjadi bila hasilnya tidak negatif.
        $stock = $this->pdo->prepare(
            'UPDATE product_stock
             SET quantity = quantity + :delta
             WHERE product_sku = :sku AND warehouse_id = :warehouse_id AND quantity + :delta_check >= 0'
        );
        $stock->execute([
            'delta' => $change->delta,
            'delta_check' => $change->delta,
            'sku' => $change->sku,
            'warehouse_id' => $change->warehouseId,
        ]);
        if ($stock->rowCount() !== 1) {
            throw new InsufficientStockException($change->sku, $change->warehouseId);
        }
    }

    public function movementsByReference(string $refType, string $refId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.created_at, w.name AS warehouse_name, l.movement_type, l.quantity, l.ref_id, l.product_sku
             FROM stock_ledger l
             JOIN warehouses w ON w.id = l.warehouse_id
             WHERE l.ref_type = :ref_type AND l.ref_id = :ref_id
             ORDER BY l.created_at, l.id'
        );
        $stmt->execute(['ref_type' => $refType, 'ref_id' => $refId]);

        return array_values(array_map(static fn (array $row): StockMovement => new StockMovement(
            (string) $row['created_at'],
            (string) $row['warehouse_name'],
            (string) $row['movement_type'],
            (int) $row['quantity'],
            (string) $row['ref_id'],
            (string) $row['product_sku'],
        ), $stmt->fetchAll()));
    }
}
