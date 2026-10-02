<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\Role;
use App\Repository\MySqlUserRepository;
use App\Service\AuthService;

/**
 * AuthService + MySqlUserRepository dengan hash bcrypt asli dari seed.
 */
final class AuthIntegrationTest extends IntegrationTestCase
{
    public function testSeedAccountsCanLoginWithPasswordVerify(): void
    {
        $auth = new AuthService(new MySqlUserRepository($this->pdo));

        self::assertSame(Role::Admin, $auth->attempt('admin@ioms.test', 'admin123')?->role);
        self::assertSame(Role::Sales, $auth->attempt('sinta@ioms.test', 'sales123')?->role);
        self::assertSame(Role::WarehouseStaff, $auth->attempt('rudi@ioms.test', 'gudang123')?->role);
    }

    public function testInactiveSeedAccountIsRejected(): void
    {
        $auth = new AuthService(new MySqlUserRepository($this->pdo));

        self::assertNull($auth->attempt('agus@ioms.test', 'gudang123'));
    }

    public function testPasswordsAreStoredAsHashesNotPlaintext(): void
    {
        $stmt = $this->pdo->query('SELECT password FROM users');
        self::assertNotFalse($stmt);

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $stored) {
            self::assertIsString($stored);
            // Format bcrypt: $2a$/$2b$/$2y$ + cost 2 digit + 53 karakter salt+hash.
            self::assertMatchesRegularExpression('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $stored);
        }
    }
}
