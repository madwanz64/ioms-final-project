<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Jenis Party. Nilai enum = nama tabel MySQL, sehingga nama tabel di
 * MySqlPartyRepository hanya bisa salah satu dari dua nilai tetap ini.
 */
enum PartyType: string
{
    case Supplier = 'suppliers';
    case Customer = 'customers';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Supplier',
            self::Customer => 'Customer',
        };
    }

    /**
     * Prefix URL halaman, mis. "/suppliers".
     */
    public function path(): string
    {
        return '/' . $this->value;
    }
}
