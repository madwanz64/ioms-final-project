<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * Data tidak ada ATAU tidak boleh dilihat user ini (dipetakan menjadi 404,
 * sehingga keberadaan data milik orang lain tidak terungkap).
 */
final class NotFoundException extends RuntimeException
{
}
