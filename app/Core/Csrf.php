<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF per session. Router memeriksanya untuk SEMUA request POST,
 * sehingga form login, logout, dan simpan produk ikut terlindungi.
 */
final class Csrf
{
    public const FIELD = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::FIELD);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::FIELD, $token);
        }

        return $token;
    }

    public function isValid(string $submitted): bool
    {
        $token = $this->session->get(self::FIELD);

        return is_string($token) && $token !== '' && hash_equals($token, $submitted);
    }
}
