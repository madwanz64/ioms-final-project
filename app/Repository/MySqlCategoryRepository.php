<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use PDO;

final class MySqlCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, description FROM categories ORDER BY name');
        $rows = $stmt === false ? [] : $stmt->fetchAll();

        return array_values(array_map(fn (array $row): Category => $this->hydrate($row), $rows));
    }

    public function findById(int $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT id, name, description FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        // Collation utf8mb4 default MySQL 8 bersifat case-insensitive.
        $stmt = $this->pdo->prepare('SELECT 1 FROM categories WHERE name = :name AND id <> :except_id');
        $stmt->execute(['name' => $name, 'except_id' => $exceptId ?? 0]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(Category $category): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
        $stmt->execute(['name' => $category->name, 'description' => $category->description]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(Category $category): void
    {
        $stmt = $this->pdo->prepare('UPDATE categories SET name = :name, description = :description WHERE id = :id');
        $stmt->execute(['id' => $category->id, 'name' => $category->name, 'description' => $category->description]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Category
    {
        return new Category(
            (int) $row['id'],
            (string) $row['name'],
            $row['description'] === null ? null : (string) $row['description'],
        );
    }
}
