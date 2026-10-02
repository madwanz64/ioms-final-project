<?php

declare(strict_types=1);

namespace App\Core;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

/**
 * Status login pada session. Session hanya menyimpan user id; data user
 * (termasuk role & status aktif) dimuat ulang dari database di setiap
 * request, sehingga user yang dinonaktifkan Admin langsung kehilangan akses
 * dan perubahan role langsung berlaku.
 */
final class Auth
{
    private const SESSION_KEY = 'user_id';

    private ?User $resolved = null;
    private bool $loaded = false;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function user(): ?User
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = $this->session->get(self::SESSION_KEY);
            $user = is_int($id) ? $this->users->findById($id) : null;
            if ($user !== null && !$user->active) {
                $this->session->remove(self::SESSION_KEY);
                $user = null;
            }
            $this->resolved = $user;
        }

        return $this->resolved;
    }

    public function login(User $user): void
    {
        // ID session baru setelah login mencegah session fixation (AUTH-01).
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->id);
        $this->resolved = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->resolved = null;
        $this->loaded = true;
    }
}
