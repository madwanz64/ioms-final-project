<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Nilai tetap role sesuai brief §1.3. Nilai string = isi kolom users.role.
 */
enum Role: string
{
    case Admin = 'Admin';
    case Sales = 'Sales';
    case WarehouseStaff = 'Warehouse Staff';
}
