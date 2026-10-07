<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Entity\Warehouse;
use PDO;

final class MySqlWarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, location, active FROM warehouses ORDER BY name');
        $rows = $stmt === false ? [] : $stmt->fetchAll();

        return array_values(array_map(fn (array $row): Warehouse => $this->hydrate($row), $rows));
    }

    public function findById(int $id): ?Warehouse
    {
        $stmt = $this->pdo->prepare('SELECT id, name, location, active FROM warehouses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM warehouses WHERE name = :name AND id <> :except_id');
        $stmt->execute(['name' => $name, 'except_id' => $exceptId ?? 0]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(Warehouse $warehouse): int
    {
        return Database::transactional($this->pdo, function () use ($warehouse): int {
            $stmt = $this->pdo->prepare('INSERT INTO warehouses (name, location, active) VALUES (:name, :location, :active)');
            $stmt->execute(['name' => $warehouse->name, 'location' => $warehouse->location, 'active' => $warehouse->active ? 1 : 0]);
            $id = (int) $this->pdo->lastInsertId();

            // Gudang baru: baris stok 0 untuk setiap produk (WH-01). Stok bertambah
            // hanya lewat goods receipt yang menulis stock ledger.
            $stock = $this->pdo->prepare(
                'INSERT INTO product_stock (product_sku, warehouse_id, quantity)
                 SELECT sku, :warehouse_id, 0 FROM products'
            );
            $stock->execute(['warehouse_id' => $id]);

            return $id;
        });
    }

    public function update(Warehouse $warehouse): void
    {
        $stmt = $this->pdo->prepare('UPDATE warehouses SET name = :name, location = :location, active = :active WHERE id = :id');
        $stmt->execute([
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'location' => $warehouse->location,
            'active' => $warehouse->active ? 1 : 0,
        ]);
    }

    public function stockTotals(): array
    {
        $stmt = $this->pdo->query('SELECT warehouse_id, SUM(quantity) FROM product_stock GROUP BY warehouse_id');
        $totals = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $result = [];
        foreach ($totals as $warehouseId => $total) {
            $result[(int) $warehouseId] = (int) $total;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Warehouse
    {
        return new Warehouse((int) $row['id'], (string) $row['name'], (string) $row['location'], (bool) $row['active']);
    }
}
