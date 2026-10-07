<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

/**
 * Aturan login (AUTH-01). Tidak tahu soal session — controller yang
 * menyimpan hasilnya ke session, sehingga service ini bisa diuji tanpa HTTP.
 */
final class AuthService
{
    /**
     * Hash dummy agar waktu respons email-tidak-terdaftar mirip dengan
     * password-salah (mengurangi kebocoran "email ini ada" lewat timing).
     */
    private const DUMMY_HASH = '$2y$10$.BDyZmXxCpHqBtf.P6jBr.5EH.0N.pwIb1/TkqFrfZQlUhfAc/uiK';

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    /**
     * Mengembalikan user bila kredensial valid DAN akun aktif; selain itu null.
     * Pemanggil menampilkan satu pesan generik untuk semua kegagalan, tanpa
     * menjelaskan bagian mana yang salah.
     */
    public function attempt(string $email, string $password): ?User
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || $password === '') {
            return null;
        }

        $user = $this->users->findByEmail($email);
        // Email tak terdaftar tetap diverifikasi terhadap DUMMY_HASH (anti timing).
        $valid = password_verify($password, $user->passwordHash ?? self::DUMMY_HASH);

        return $valid && $user !== null && $user->active ? $user : null;
    }
}
