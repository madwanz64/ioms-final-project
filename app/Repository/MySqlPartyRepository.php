<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Party;
use App\Entity\PartyType;
use PDO;

/**
 * Satu implementasi untuk tabel suppliers dan customers (struktur identik).
 * Nama tabel berasal dari enum PartyType — bukan dari input user — sehingga
 * aman disisipkan ke SQL; semua nilai data tetap lewat parameter terikat.
 */
final class MySqlPartyRepository implements PartyRepositoryInterface
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, PartyType $type)
    {
        $this->table = $type->value;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, contact, address, active FROM ' . $this->table . ' ORDER BY active DESC, name');
        $rows = $stmt === false ? [] : $stmt->fetchAll();

        return array_values(array_map(fn (array $row): Party => $this->hydrate($row), $rows));
    }

    public function findById(int $id): ?Party
    {
        $stmt = $this->pdo->prepare('SELECT id, name, contact, address, active FROM ' . $this->table . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function create(Party $party): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $this->table . ' (name, contact, address, active) VALUES (:name, :contact, :address, :active)'
        );
        $stmt->execute($this->params($party));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(Party $party): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . $this->table . ' SET name = :name, contact = :contact, address = :address, active = :active WHERE id = :id'
        );
        $stmt->execute(['id' => $party->id] + $this->params($party));
    }

    /**
     * @return array<string, string|int>
     */
    private function params(Party $party): array
    {
        return ['name' => $party->name, 'contact' => $party->contact, 'address' => $party->address, 'active' => $party->active ? 1 : 0];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Party
    {
        return new Party((int) $row['id'], (string) $row['name'], (string) $row['contact'], (string) $row['address'], (bool) $row['active']);
    }
}
