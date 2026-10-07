<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\StockChange;
use App\Repository\InsufficientStockException;
use App\Repository\MySqlStockRepository;
use App\Service\BusinessRuleException;
use App\Service\StockService;
use PDOException;

/**
 * Bukti ADR-001 / ARCH-02 pada MySQL sungguhan. Seed: SKU-0001 di gudang 1 = 3 unit.
 *
 * Skenario terkontrol (tanpa thread/sleep):
 *  1. Saat request A mengunci baris stok, request B (koneksi lain) tidak bisa
 *     membaca-untuk-mengubah baris itu -> B tertunda (di sini: ditolak NOWAIT).
 *  2. Setelah A menghabiskan stok, permintaan B ditolak; stok tidak negatif.
 *  3. Guard SQL tetap menolak walau lock dilewati.
 */
final class StockConcurrencyTest extends IntegrationTestCase
{
    public function testSecondConnectionCannotTouchStockRowLockedByFirstTransaction(): void
    {
        // Request A: transaksi berjalan (dibuka IntegrationTestCase), baris dikunci.
        (new MySqlStockRepository($this->pdo))->lockQuantities([['sku' => 'SKU-0001', 'warehouseId' => 1]]);

        // Request B di koneksi lain mencoba mengunci baris yang sama.
        $other = self::secondConnection();
        $other->beginTransaction();
        try {
            $other->query("SELECT quantity FROM product_stock WHERE product_sku = 'SKU-0001' AND warehouse_id = 1 FOR UPDATE NOWAIT");
            self::fail('Koneksi kedua seharusnya tidak bisa mengunci baris yang sedang dikunci.');
        } catch (PDOException $e) {
            // 3572 = ER_LOCK_NOWAIT: baris sedang dikunci transaksi lain. Tanpa NOWAIT,
            // request B akan MENUNGGU sampai A commit/rollback, lalu membaca angka terbaru.
            self::assertSame(3572, (int) ($e->errorInfo[1] ?? 0));
        } finally {
            $other->rollBack();
        }

        // Baris lain tetap bebas — lock hanya pada produk+gudang yang diproses.
        $free = self::secondConnection();
        $free->beginTransaction();
        $stmt = $free->query("SELECT quantity FROM product_stock WHERE product_sku = 'SKU-0002' AND warehouse_id = 1 FOR UPDATE NOWAIT");
        self::assertNotFalse($stmt);
        self::assertSame(1, (int) $stmt->fetchColumn());
        $free->rollBack();
    }

    public function testSecondIssueIsRejectedAfterFirstIssueUsedUpTheStock(): void
    {
        $repo = new MySqlStockRepository($this->pdo);
        $service = new StockService($repo);

        // Goods issue pertama menghabiskan seluruh stok (3).
        $service->apply([$this->issue('SKU-0001', 3, 'SO-UJI-1')]);

        try {
            // Goods issue kedua (1 unit) datang setelahnya -> harus ditolak.
            $service->apply([$this->issue('SKU-0001', 1, 'SO-UJI-2')]);
            self::fail('Goods issue kedua seharusnya ditolak.');
        } catch (BusinessRuleException $e) {
            self::assertStringContainsString('tersedia 0, dibutuhkan 1', $e->getMessage());
        }

        self::assertSame(0, $this->quantity('SKU-0001', 1));
        self::assertSame(0, $this->ledgerSum('SKU-0001', 1), 'product_stock harus tetap sama dengan SUM(stock_ledger)');
        self::assertSame(1, $this->ledgerRows('SO-UJI-1'));
        self::assertSame(0, $this->ledgerRows('SO-UJI-2'));
    }

    public function testSqlGuardRejectsOversellEvenWithoutLockAndWritesNothing(): void
    {
        $repo = new MySqlStockRepository($this->pdo);
        $this->pdo->exec('SAVEPOINT before_guard');

        try {
            // Memanggil recordMovement langsung (melewati lockQuantities & cek Service).
            $repo->recordMovement($this->issue('SKU-0001', 4, 'SO-UJI-3'));
            self::fail('Guard SQL seharusnya menolak.');
        } catch (InsufficientStockException $e) {
            self::assertSame('SKU-0001', $e->sku);
        }
        // Di aplikasi, exception membatalkan seluruh transaksi; di sini disimulasikan dengan savepoint.
        $this->pdo->exec('ROLLBACK TO SAVEPOINT before_guard');

        self::assertSame(3, $this->quantity('SKU-0001', 1));
        self::assertSame(0, $this->ledgerRows('SO-UJI-3'));
    }

    public function testDatabaseCheckConstraintIsTheLastLineOfDefence(): void
    {
        $this->expectException(PDOException::class);

        // Update langsung tanpa guard pun tetap ditolak CHECK (quantity >= 0).
        $this->pdo->exec("UPDATE product_stock SET quantity = quantity - 100 WHERE product_sku = 'SKU-0001' AND warehouse_id = 1");
    }

    private function issue(string $sku, int $qty, string $ref): StockChange
    {
        return new StockChange($sku, 1, -$qty, StockChange::TYPE_ISSUE, 'SO', $ref, 4);
    }

    private function quantity(string $sku, int $warehouseId): int
    {
        $stmt = $this->pdo->prepare('SELECT quantity FROM product_stock WHERE product_sku = :sku AND warehouse_id = :w');
        $stmt->execute(['sku' => $sku, 'w' => $warehouseId]);

        return (int) $stmt->fetchColumn();
    }

    private function ledgerSum(string $sku, int $warehouseId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM stock_ledger WHERE product_sku = :sku AND warehouse_id = :w');
        $stmt->execute(['sku' => $sku, 'w' => $warehouseId]);

        return (int) $stmt->fetchColumn();
    }

    private function ledgerRows(string $refId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM stock_ledger WHERE ref_id = :ref');
        $stmt->execute(['ref' => $refId]);

        return (int) $stmt->fetchColumn();
    }
}
