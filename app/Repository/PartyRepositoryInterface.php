<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Party;

/**
 * Supplier dan Customer. Tidak ada delete: keduanya hanya dinonaktifkan (§1.3).
 */
interface PartyRepositoryInterface
{
    /**
     * @return list<Party>
     */
    public function all(): array;

    public function findById(int $id): ?Party;

    /**
     * @return int id baru
     */
    public function create(Party $party): int;

    public function update(Party $party): void;
}
