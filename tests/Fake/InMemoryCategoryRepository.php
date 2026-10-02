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
}
