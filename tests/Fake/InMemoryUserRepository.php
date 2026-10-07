<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $users = [];

    public function add(User $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $user = $this->findByEmail($email);

        return $user !== null && $user->id !== $exceptId;
    }

    public function create(User $user): int
    {
        $id = $this->users === [] ? 1 : max(array_keys($this->users)) + 1;
        $this->users[$id] = new User($id, $user->name, $user->email, $user->passwordHash, $user->role, $user->active);

        return $id;
    }

    public function update(User $user): void
    {
        $this->users[$user->id] = $user;
    }
}
