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
