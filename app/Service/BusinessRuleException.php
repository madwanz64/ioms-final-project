<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Aksi ditolak karena aturan bisnis (mis. transisi status tidak sah, stok
 * tidak cukup). Pesannya aman ditampilkan ke user.
 */
final class BusinessRuleException extends RuntimeException
{
}
