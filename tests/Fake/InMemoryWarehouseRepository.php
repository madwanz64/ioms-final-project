<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\Warehouse;
use App\Repository\WarehouseRepositoryInterface;

final class InMemoryWarehouseRepository implements WarehouseRepositoryInterface
{
    /** @var array<int, Warehouse> */
    private array $warehouses = [];

    public function add(Warehouse $warehouse): void
    {
        $this->warehouses[$warehouse->id] = $warehouse;
    }

    public function all(): array
    {
        return array_values($this->warehouses);
    }

    public function findById(int $id): ?Warehouse
    {
        return $this->warehouses[$id] ?? null;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        foreach ($this->warehouses as $warehouse) {
            if ($warehouse->id !== $exceptId && mb_strtolower($warehouse->name) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function create(Warehouse $warehouse): int
    {
        $id = $this->warehouses === [] ? 1 : max(array_keys($this->warehouses)) + 1;
        $this->warehouses[$id] = new Warehouse($id, $warehouse->name, $warehouse->location, $warehouse->active);

        return $id;
    }

    public function update(Warehouse $warehouse): void
    {
        $this->warehouses[$warehouse->id] = $warehouse;
    }

    public function stockTotals(): array
    {
        return [];
    }
}
