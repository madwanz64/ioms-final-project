<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use App\Entity\User;
use PDO;

final class MySqlUserRepository implements UserRepositoryInterface
{
    private const COLUMNS = 'id, name, email, password, role, active';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $this->hydrate($stmt->fetch());
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return $this->hydrate($stmt->fetch());
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT ' . self::COLUMNS . ' FROM users ORDER BY FIELD(role, \'Admin\', \'Sales\', \'Warehouse Staff\'), name');
        $users = [];
        foreach ($stmt === false ? [] : $stmt->fetchAll() as $row) {
            $user = $this->hydrate($row);
            if ($user !== null) {
                $users[] = $user;
            }
        }

        return $users;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :except_id');
        $stmt->execute(['email' => $email, 'except_id' => $exceptId ?? 0]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(User $user): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, role, active) VALUES (:name, :email, :password, :role, :active)'
        );
        $stmt->execute($this->params($user));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET name = :name, email = :email, password = :password, role = :role, active = :active WHERE id = :id'
        );
        $stmt->execute(['id' => $user->id] + $this->params($user));
    }

    /**
     * @return array<string, string|int>
     */
    private function params(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->passwordHash,
            'role' => $user->role->value,
            'active' => $user->active ? 1 : 0,
        ];
    }

    private function hydrate(mixed $row): ?User
    {
        if (!is_array($row)) {
            return null;
        }

        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password'],
            Role::from((string) $row['role']),
            (bool) $row['active'],
        );
    }
}
