<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Role;
use App\Entity\User;
use App\Service\AuthService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryUserRepository;

final class AuthServiceTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        $users = new InMemoryUserRepository();
        // Cost 4 (minimum bcrypt) supaya test tetap cepat (FIRST: Fast).
        $hash = password_hash('rahasia123', PASSWORD_BCRYPT, ['cost' => 4]);
        $users->add(new User(1, 'Admin', 'admin@ioms.test', $hash, Role::Admin, true));
        $users->add(new User(2, 'Gudang Nonaktif', 'off@ioms.test', $hash, Role::WarehouseStaff, false));
        $this->auth = new AuthService($users);
    }

    public function testValidCredentialsReturnUser(): void
    {
        $user = $this->auth->attempt('admin@ioms.test', 'rahasia123');

        self::assertNotNull($user);
        self::assertSame(1, $user->id);
        self::assertSame(Role::Admin, $user->role);
    }

    public function testEmailIsCaseInsensitiveAndTrimmed(): void
    {
        self::assertNotNull($this->auth->attempt('  ADMIN@ioms.TEST ', 'rahasia123'));
    }

    /**
     * Semua kegagalan menghasilkan null yang sama, sehingga controller tidak
     * bisa (dan tidak perlu) membedakan bagian mana yang salah (AUTH-01).
     */
    #[DataProvider('failedLogins')]
    public function testFailedLoginReturnsNull(string $email, string $password): void
    {
        self::assertNull($this->auth->attempt($email, $password));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function failedLogins(): array
    {
        return [
            'password salah' => ['admin@ioms.test', 'salah'],
            'email tidak terdaftar' => ['siapa@ioms.test', 'rahasia123'],
            'akun nonaktif walau password benar' => ['off@ioms.test', 'rahasia123'],
            'password kosong' => ['admin@ioms.test', ''],
            'email kosong' => ['', 'rahasia123'],
        ];
    }
}
