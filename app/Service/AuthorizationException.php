<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * User tidak berwenang melakukan aksi ini (dipetakan controller menjadi 403).
 */
final class AuthorizationException extends RuntimeException
{
}
