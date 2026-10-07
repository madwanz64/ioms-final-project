<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepositoryInterface;

/**
 * Manajemen user oleh Admin (USR-01). Akun hanya dibuat lewat sini — tidak
 * ada public registration. Password selalu disimpan sebagai hash (§4.2).
 */
final class UserService
{
    public const MIN_PASSWORD_LENGTH = 8;
    // bcrypt hanya memakai 72 byte pertama; lebih dari itu ditolak agar tidak menyesatkan.
    private const MAX_PASSWORD_BYTES = 72;

    /**
     * @param int $bcryptCost cost bcrypt; unit test memakai nilai minimum (4) agar cepat
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly int $bcryptCost = 10,
    ) {
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        return $this->users->all();
    }

    public function find(int $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $validator = new InputValidator($input);
        [$name, $email, $role, $active] = $this->validateProfile($validator, null);
        $password = $this->validatePassword($validator, $input, true);
        $validator->throwIfInvalid();

        $user = new User(0, $name, $email, $this->hash((string) $password), $role, $active);

        return new User($this->users->create($user), $user->name, $user->email, $user->passwordHash, $user->role, $user->active);
    }

    /**
     * Password kosong = tidak diganti. Admin tidak boleh menonaktifkan atau
     * mengubah role akunnya sendiri, supaya tidak mengunci diri keluar dari
     * aplikasi atau kehilangan akses administrasi tanpa sengaja.
     *
     * @param array<string, string> $input
     * @throws ValidationException
     */
    public function update(User $existing, array $input, User $actor): User
    {
        $validator = new InputValidator($input);
        [$name, $email, $role, $active] = $this->validateProfile($validator, $existing->id);
        $password = $this->validatePassword($validator, $input, false);

        if ($actor->id === $existing->id) {
            if (!$active && !$validator->hasError('active')) {
                $validator->addError('active', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            }
            if ($role !== null && $role !== $existing->role) {
                $validator->addError('role', 'Anda tidak dapat mengubah role akun Anda sendiri.');
            }
        }
        $validator->throwIfInvalid();

        $hash = $password === null ? $existing->passwordHash : $this->hash($password);
        $user = new User($existing->id, $name, $email, $hash, $role ?? $existing->role, $active);
        $this->users->update($user);

        return $user;
    }

    /**
     * @return array{string, string, Role|null, bool}
     */
    private function validateProfile(InputValidator $validator, ?int $userId): array
    {
        $name = $validator->requiredText('name', 'Nama', 150);

        $email = mb_strtolower($validator->requiredText('email', 'Email', 150));
        if (!$validator->hasError('email') && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $validator->addError('email', 'Format email tidak valid.');
        }
        if (!$validator->hasError('email') && $this->users->emailExists($email, $userId)) {
            $validator->addError('email', 'Email sudah dipakai user lain.');
        }

        $role = Role::tryFrom($validator->raw('role'));
        if ($role === null) {
            $validator->addError('role', 'Pilih role yang valid.');
        }

        return [$name, $email, $role, $validator->activeFlag()];
    }

    /**
     * Catatan: Request sudah men-trim semua input, termasuk password. Form
     * login juga di-trim dengan cara yang sama, jadi perilakunya konsisten.
     *
     * @param array<string, string> $input
     */
    private function validatePassword(InputValidator $validator, array $input, bool $required): ?string
    {
        $password = $input['password'] ?? '';
        if ($password === '' && !$required) {
            return null;
        }
        if ($password === '') {
            $validator->addError('password', 'Password wajib diisi.');
        } elseif (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $validator->addError('password', sprintf('Password minimal %d karakter.', self::MIN_PASSWORD_LENGTH));
        } elseif (strlen($password) > self::MAX_PASSWORD_BYTES) {
            $validator->addError('password', 'Password terlalu panjang (maksimal 72 byte).');
        } elseif ($password !== ($input['password_confirmation'] ?? '')) {
            $validator->addError('password_confirmation', 'Konfirmasi password tidak sama.');
        }

        return $password;
    }

    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->bcryptCost]);
    }
}
