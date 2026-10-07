<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\Category;
use App\Repository\CategoryRepositoryInterface;

final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<int, Category> */
    private array $categories = [];

    public function add(Category $category): void
    {
        $this->categories[$category->id] = $category;
    }

    public function all(): array
    {
        return array_values($this->categories);
    }

    public function findById(int $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        foreach ($this->categories as $category) {
            // Meniru collation MySQL yang case-insensitive.
            if ($category->id !== $exceptId && mb_strtolower($category->name) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function create(Category $category): int
    {
        $id = $this->categories === [] ? 1 : max(array_keys($this->categories)) + 1;
        $this->categories[$id] = new Category($id, $category->name, $category->description);

        return $id;
    }

    public function update(Category $category): void
    {
        $this->categories[$category->id] = $category;
    }
}
