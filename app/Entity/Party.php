<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Supplier atau Customer. Brief §1.3 memberi keduanya field yang sama
 * (nama, kontak, alamat, status aktif), jadi dipakai satu entity; jenisnya
 * ditentukan PartyType pada repository/controller.
 */
final class Party
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $contact,
        public readonly string $address,
        public readonly bool $active,
    ) {
    }
}
