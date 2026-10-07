<?php

declare(strict_types=1);

namespace App\Repository;

use RuntimeException;

/**
 * Dilempar guard SQL `quantity + delta >= 0` (lapis kedua ADR-001) bila
 * perubahan stok akan membuat quantity negatif.
 */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $sku, public readonly int $warehouseId)
    {
        parent::__construct(sprintf('Stok %s di gudang #%d tidak mencukupi.', $sku, $warehouseId));
    }
}
