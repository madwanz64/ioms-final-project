<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

interface CategoryRepositoryInterface
{
    /**
     * @return list<Category>
     */
    public function all(): array;

    public function findById(int $id): ?Category;

    /**
     * Nama sudah dipakai kategori lain (case-insensitive), kecuali $exceptId.
     */
    public function nameExists(string $name, ?int $exceptId = null): bool;

    /**
     * @return int id kategori baru
     */
    public function create(Category $category): int;

    public function update(Category $category): void;
}
