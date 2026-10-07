<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Warehouse;
use App\Repository\WarehouseRepositoryInterface;

/**
 * Master gudang (WH-01). Gudang tidak dihapus karena direferensikan stok,
 * order, dan ledger; cukup dinonaktifkan.
 */
final class WarehouseService
{
    public function __construct(private readonly WarehouseRepositoryInterface $warehouses)
    {
    }

    /**
     * @return list<Warehouse>
     */
    public function all(): array
    {
        return $this->warehouses->all();
    }

    /**
     * @return array<int, int>
     */
    public function stockTotals(): array
    {
        return $this->warehouses->stockTotals();
    }

    public function find(int $id): ?Warehouse
    {
        return $this->warehouses->findById($id);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function create(array $input): Warehouse
    {
        $warehouse = $this->validated(0, $input);

        return new Warehouse($this->warehouses->create($warehouse), $warehouse->name, $warehouse->location, $warehouse->active);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function update(Warehouse $existing, array $input): Warehouse
    {
        $warehouse = $this->validated($existing->id, $input);
        $this->warehouses->update($warehouse);

        return $warehouse;
    }

    /**
     * @param array<string, string> $input
     */
    private function validated(int $id, array $input): Warehouse
    {
        $validator = new InputValidator($input);
        $name = $validator->requiredText('name', 'Nama gudang', 150);
        if (!$validator->hasError('name') && $this->warehouses->nameExists($name, $id === 0 ? null : $id)) {
            $validator->addError('name', 'Nama gudang sudah dipakai.');
        }
        $location = $validator->requiredText('location', 'Lokasi', 255);
        $active = $validator->activeFlag();
        $validator->throwIfInvalid();

        return new Warehouse($id, $name, $location, $active);
    }
}
