<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Kegagalan infrastruktur inti (template view tidak ada, buffer CSV tidak bisa dibuat).
 */
final class InfrastructureException extends RuntimeException
{
}
