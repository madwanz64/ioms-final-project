<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Role;
use App\Entity\User;
use App\Service\UserService;
use App\Service\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryUserRepository;

/**
 * Area logic: manajemen user oleh Admin (USR-01).
 */
final class UserServiceTest extends TestCase
{
    private InMemoryUserRepository $users;
    private UserService $service;
    private User $admin;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->admin = new User(1, 'Admin', 'admin@ioms.test', password_hash('admin12345', PASSWORD_BCRYPT, ['cost' => 4]), Role::Admin, true);
        $this->users->add($this->admin);
        $this->users->add(new User(2, 'Sinta', 'sinta@ioms.test', password_hash('sales12345', PASSWORD_BCRYPT, ['cost' => 4]), Role::Sales, true));
        $this->service = new UserService($this->users, bcryptCost: 4);
    }

    public function testCreateHashesPasswordAndNormalisesEmail(): void
    {
        $user = $this->service->create($this->validInput(['email' => ' Rina@IOMS.test ']));

        self::assertSame('rina@ioms.test', $user->email);
        self::assertSame(Role::WarehouseStaff, $user->role);
        self::assertNotSame('rahasia123', $user->passwordHash);
        self::assertTrue(password_verify('rahasia123', $user->passwordHash));
        self::assertNotNull($this->users->findByEmail('rina@ioms.test'));
    }

    public function testEmailMustBeUniqueRegardlessOfCase(): void
    {
        $errors = $this->createErrors(['email' => 'SINTA@ioms.test']);

        self::assertSame('Email sudah dipakai user lain.', $errors['email']);
    }

    /**
     * @param array<string, string> $overrides
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejectedAndNothingSaved(array $overrides, string $field): void
    {
        $errors = $this->createErrors($overrides);

        self::assertArrayHasKey($field, $errors);
        self::assertCount(2, $this->users->all());
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'role di luar tiga nilai tetap' => [['role' => 'Supervisor'], 'role'],
            'format email salah' => [['email' => 'bukan-email'], 'email'],
            'password terlalu pendek' => [['password' => 'abc123', 'password_confirmation' => 'abc123'], 'password'],
            'password kosong saat create' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'konfirmasi tidak sama' => [['password_confirmation' => 'lain12345'], 'password_confirmation'],
            'password > 72 byte' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
        ];
    }

    public function testUpdateWithEmptyPasswordKeepsExistingHash(): void
    {
        $sinta = $this->users->findById(2);
        self::assertNotNull($sinta);

        $updated = $this->service->update($sinta, $this->validInput(['email' => 'sinta@ioms.test', 'role' => 'Sales', 'password' => '', 'password_confirmation' => '']), $this->admin);

        self::assertSame($sinta->passwordHash, $updated->passwordHash);
        self::assertTrue(password_verify('sales12345', $updated->passwordHash));
    }

    public function testAdminCanDeactivateOtherUser(): void
    {
        $sinta = $this->users->findById(2);
        self::assertNotNull($sinta);

        $updated = $this->service->update($sinta, $this->validInput(['email' => 'sinta@ioms.test', 'role' => 'Sales', 'active' => '0', 'password' => '', 'password_confirmation' => '']), $this->admin);

        self::assertFalse($updated->active);
    }

    public function testAdminCannotDeactivateOrDemoteOwnAccount(): void
    {
        try {
            $this->service->update($this->admin, $this->validInput([
                'email' => 'admin@ioms.test', 'role' => 'Sales', 'active' => '0', 'password' => '', 'password_confirmation' => '',
            ]), $this->admin);
            self::fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('active', $e->errors);
            self::assertArrayHasKey('role', $e->errors);
        }

        $stored = $this->users->findById(1);
        self::assertNotNull($stored);
        self::assertTrue($stored->active);
        self::assertSame(Role::Admin, $stored->role);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function createErrors(array $overrides): array
    {
        try {
            $this->service->create($this->validInput($overrides));
        } catch (ValidationException $e) {
            return $e->errors;
        }
        self::fail('ValidationException seharusnya dilempar.');
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Rina Gudang',
            'email' => 'rina@ioms.test',
            'role' => 'Warehouse Staff',
            'active' => '1',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];
    }
}
