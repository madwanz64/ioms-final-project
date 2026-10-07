<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return list<User>
     */
    public function all(): array;

    public function emailExists(string $email, ?int $exceptId = null): bool;

    /**
     * @return int id user baru
     */
    public function create(User $user): int;

    /**
     * Menyimpan nama, email, role, status aktif, dan hash password.
     */
    public function update(User $user): void;
}
