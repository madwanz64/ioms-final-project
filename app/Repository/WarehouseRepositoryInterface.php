<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;

interface WarehouseRepositoryInterface
{
    /**
     * @return list<Warehouse>
     */
    public function all(): array;

    public function findById(int $id): ?Warehouse;

    public function nameExists(string $name, ?int $exceptId = null): bool;

    /**
     * Simpan gudang baru sekaligus membuat baris stok 0 untuk setiap produk,
     * agar invarian WH-01 "setiap produk punya baris stok per gudang" terjaga.
     *
     * @return int id gudang baru
     */
    public function create(Warehouse $warehouse): int;

    public function update(Warehouse $warehouse): void;

    /**
     * Total quantity seluruh produk di gudang ini (untuk informasi di daftar).
     *
     * @return array<int, int> warehouseId => total quantity
     */
    public function stockTotals(): array;
}
