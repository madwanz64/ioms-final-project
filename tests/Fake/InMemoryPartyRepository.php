<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\Party;
use App\Repository\PartyRepositoryInterface;

final class InMemoryPartyRepository implements PartyRepositoryInterface
{
    /** @var array<int, Party> */
    private array $parties = [];

    public function add(Party $party): void
    {
        $this->parties[$party->id] = $party;
    }

    public function all(): array
    {
        return array_values($this->parties);
    }

    public function findById(int $id): ?Party
    {
        return $this->parties[$id] ?? null;
    }

    public function create(Party $party): int
    {
        $id = $this->parties === [] ? 1 : max(array_keys($this->parties)) + 1;
        $this->parties[$id] = new Party($id, $party->name, $party->contact, $party->address, $party->active);

        return $id;
    }

    public function update(Party $party): void
    {
        $this->parties[$party->id] = $party;
    }
}
