<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Entity\StockChange;
use App\Repository\InsufficientStockException;
use App\Repository\StockRepositoryInterface;

/**
 * Stok di memori, meniru guard SQL `quantity + delta >= 0` dari MySqlStockRepository.
 */
final class InMemoryStockRepository implements StockRepositoryInterface
{
    /** @var array<string, int> */
    public array $quantities = [];

    /** @var list<StockChange> ledger yang tercatat */
    public array $ledger = [];

    /** @var list<string> urutan kunci yang di-lock, untuk memeriksa urutan anti-deadlock */
    public array $lockOrder = [];

    public function set(string $sku, int $warehouseId, int $quantity): void
    {
        $this->quantities[StockChange::key($sku, $warehouseId)] = $quantity;
    }

    public function quantity(string $sku, int $warehouseId): int
    {
        return $this->quantities[StockChange::key($sku, $warehouseId)] ?? 0;
    }

    public function lockQuantities(array $keys): array
    {
        usort($keys, static fn (array $a, array $b): int => [$a['sku'], $a['warehouseId']] <=> [$b['sku'], $b['warehouseId']]);
        $result = [];
        foreach ($keys as $key) {
            $k = StockChange::key($key['sku'], $key['warehouseId']);
            $this->lockOrder[] = $k;
            $result[$k] = $this->quantities[$k] ?? 0;
        }

        return $result;
    }

    public function recordMovement(StockChange $change): void
    {
        $key = StockChange::key($change->sku, $change->warehouseId);
        $current = $this->quantities[$key] ?? 0;
        if ($current + $change->delta < 0) {
            throw new InsufficientStockException($change->sku, $change->warehouseId);
        }
        $this->ledger[] = $change;
        $this->quantities[$key] = $current + $change->delta;
    }

    public function movementsByReference(string $refType, string $refId): array
    {
        return [];
    }
}
