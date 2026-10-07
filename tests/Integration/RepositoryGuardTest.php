<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Role;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockRepository;
use App\Repository\MySqlUserRepository;
use App\Repository\PersistenceException;

/**
 * Pengaman di lapisan repository: lock hanya sah di dalam transaksi (ADR-001),
 * dan penerimaan barang tidak bisa melebihi qty pesanan walau Service terlewati.
 */
final class RepositoryGuardTest extends IntegrationTestCase
{
    public function testLocksOutsideTransactionAreRefused(): void
    {
        // Koneksi kedua tidak sedang dalam transaksi (koneksi utama selalu di dalam transaksi test).
        $pdo = self::secondConnection();
        $calls = [
            'PO' => static fn () => (new MySqlPurchaseOrderRepository($pdo))->lockById(1),
            'SO' => static fn () => (new MySqlSalesOrderRepository($pdo))->lockById(1),
            'stok' => static fn () => (new MySqlStockRepository($pdo))->lockQuantities([['sku' => 'SKU-0001', 'warehouseId' => 1]]),
        ];

        foreach ($calls as $label => $call) {
            try {
                $call();
                self::fail('Lock ' . $label . ' di luar transaksi harus ditolak.');
            } catch (PersistenceException $e) {
                self::assertStringContainsString('di dalam transaksi', $e->getMessage());
            }
        }
    }

    public function testLockingUnknownStockRowFails(): void
    {
        $this->expectException(PersistenceException::class);

        (new MySqlStockRepository($this->pdo))->lockQuantities([['sku' => 'SKU-TIDAK-ADA', 'warehouseId' => 1]]);
    }

    public function testReceivingBeyondOrderedQtyIsRefusedBySqlGuard(): void
    {
        $item = $this->pdo->query('SELECT id, qty, received_qty FROM purchase_order_items ORDER BY id LIMIT 1')->fetch();
        $remaining = (int) $item['qty'] - (int) $item['received_qty'];

        $this->expectException(PersistenceException::class);

        (new MySqlPurchaseOrderRepository($this->pdo))->addReceivedQty((int) $item['id'], $remaining + 1);
    }

    public function testUserListIsOrderedByRoleThenName(): void
    {
        $users = (new MySqlUserRepository($this->pdo))->all();
        $order = [Role::Admin->value => 0, Role::Sales->value => 1, Role::WarehouseStaff->value => 2];
        $keys = array_map(static fn ($u): array => [$order[$u->role->value], $u->name], $users);

        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys);
        self::assertSame(Role::Admin, $users[0]->role);
    }
}
