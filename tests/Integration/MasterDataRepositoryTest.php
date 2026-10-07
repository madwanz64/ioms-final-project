<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Party;
use App\Entity\PartyType;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\MySqlPartyRepository;
use App\Repository\MySqlUserRepository;
use App\Repository\MySqlWarehouseRepository;
use PDOException;

final class MasterDataRepositoryTest extends IntegrationTestCase
{
    public function testNewWarehouseGetsZeroStockRowForEveryProduct(): void
    {
        $repo = new MySqlWarehouseRepository($this->pdo);

        $id = $repo->create(new Warehouse(0, 'Gudang Uji', 'Medan', true));

        $stmt = $this->pdo->prepare('SELECT COUNT(*), SUM(quantity) FROM product_stock WHERE warehouse_id = :id');
        $stmt->execute(['id' => $id]);
        [$rows, $quantity] = $stmt->fetch(\PDO::FETCH_NUM) ?: [0, null];
        $products = $this->pdo->query('SELECT COUNT(*) FROM products');
        self::assertNotFalse($products);

        self::assertSame((int) $products->fetchColumn(), (int) $rows);
        self::assertSame(0, (int) $quantity);
        self::assertSame(0, $repo->stockTotals()[$id] ?? null);
    }

    public function testWarehouseNameCheckIsCaseInsensitiveAndExcludesItself(): void
    {
        $repo = new MySqlWarehouseRepository($this->pdo);

        self::assertTrue($repo->nameExists('GUDANG JAKARTA'));
        self::assertFalse($repo->nameExists('Gudang Jakarta', 1));
    }

    public function testSupplierAndCustomerAreStoredInTheirOwnTables(): void
    {
        $suppliers = new MySqlPartyRepository($this->pdo, PartyType::Supplier);
        $customers = new MySqlPartyRepository($this->pdo, PartyType::Customer);
        $before = count($customers->all());

        $id = $suppliers->create(new Party(0, 'PT Integrasi', '021-000', 'Jakarta', true));
        $suppliers->update(new Party($id, 'PT Integrasi', '021-999', 'Jakarta', false));

        $stored = $suppliers->findById($id);
        self::assertNotNull($stored);
        self::assertSame('021-999', $stored->contact);
        self::assertFalse($stored->active);
        self::assertCount($before, $customers->all());
    }

    public function testUserRoundTripAndDatabaseRejectsDuplicateEmail(): void
    {
        $repo = new MySqlUserRepository($this->pdo);
        $hash = password_hash('rahasia123', PASSWORD_BCRYPT, ['cost' => 4]);

        $id = $repo->create(new User(0, 'User Integrasi', 'integrasi@ioms.test', $hash, Role::WarehouseStaff, true));
        $repo->update(new User($id, 'User Integrasi', 'integrasi@ioms.test', $hash, Role::Sales, false));

        $stored = $repo->findById($id);
        self::assertNotNull($stored);
        self::assertSame(Role::Sales, $stored->role);
        self::assertFalse($stored->active);
        self::assertTrue($repo->emailExists('integrasi@ioms.test'));
        self::assertFalse($repo->emailExists('integrasi@ioms.test', $id));

        // Lapisan kedua di bawah validasi service: UNIQUE KEY uq_users_email.
        $this->expectException(PDOException::class);
        $repo->create(new User(0, 'Duplikat', 'integrasi@ioms.test', $hash, Role::Sales, true));
    }
}
