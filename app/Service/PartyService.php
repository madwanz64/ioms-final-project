<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Party;
use App\Repository\PartyRepositoryInterface;

/**
 * Master Supplier atau Customer (satu instance per jenis, dirakit di
 * composition root dengan repository yang sesuai). Tidak ada delete (§1.3).
 */
final class PartyService
{
    public function __construct(private readonly PartyRepositoryInterface $parties)
    {
    }

    /**
     * @return list<Party>
     */
    public function all(): array
    {
        return $this->parties->all();
    }

    public function find(int $id): ?Party
    {
        return $this->parties->findById($id);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function create(array $input): Party
    {
        $party = $this->validated(0, $input);

        return new Party($this->parties->create($party), $party->name, $party->contact, $party->address, $party->active);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function update(Party $existing, array $input): Party
    {
        $party = $this->validated($existing->id, $input);
        $this->parties->update($party);

        return $party;
    }

    /**
     * @param array<string, string> $input
     */
    private function validated(int $id, array $input): Party
    {
        $validator = new InputValidator($input);
        $name = $validator->requiredText('name', 'Nama', 150);
        $contact = $validator->requiredText('contact', 'Kontak', 100);
        $address = $validator->requiredText('address', 'Alamat', 255);
        $active = $validator->activeFlag();
        $validator->throwIfInvalid();

        return new Party($id, $name, $contact, $address, $active);
    }
}
