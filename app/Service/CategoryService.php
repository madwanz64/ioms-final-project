<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepositoryInterface;

final class CategoryService
{
    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    /**
     * @return list<Category>
     */
    public function all(): array
    {
        return $this->categories->all();
    }

    public function find(int $id): ?Category
    {
        return $this->categories->findById($id);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function create(array $input): Category
    {
        $category = $this->validated(0, $input);

        return new Category($this->categories->create($category), $category->name, $category->description);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function update(Category $existing, array $input): Category
    {
        $category = $this->validated($existing->id, $input);
        $this->categories->update($category);

        return $category;
    }

    /**
     * @param array<string, string> $input
     */
    private function validated(int $id, array $input): Category
    {
        $validator = new InputValidator($input);
        $name = $validator->requiredText('name', 'Nama kategori', 100);
        if (!$validator->hasError('name') && $this->categories->nameExists($name, $id === 0 ? null : $id)) {
            $validator->addError('name', 'Nama kategori sudah dipakai.');
        }
        $description = $validator->optionalText('description', 'Deskripsi', 255);
        $validator->throwIfInvalid();

        return new Category($id, $name, $description);
    }
}
