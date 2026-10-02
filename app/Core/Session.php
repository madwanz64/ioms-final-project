<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembungkus $_SESSION. Hanya lapisan Core/Controller yang memakai kelas ini;
 * Service tidak pernah menyentuh session (ARCH-01).
 */
final class Session
{
    private const FLASH_KEY = '_flash';

    public function start(bool $https): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('IOMSSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Ganti ID session (cegah session fixation) — dipanggil setelah login.
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Hapus seluruh data session beserta cookie-nya (AUTH-02).
     */
    public function destroy(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie((string) session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
        session_destroy();
    }

    public function flash(string $type, string $message): void
    {
        $flashes = $this->get(self::FLASH_KEY);
        $flashes = is_array($flashes) ? $flashes : [];
        $flashes[] = ['type' => $type, 'message' => $message];
        $this->set(self::FLASH_KEY, $flashes);
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public function pullFlashes(): array
    {
        $flashes = $this->get(self::FLASH_KEY);
        $this->remove(self::FLASH_KEY);

        /** @var list<array{type: string, message: string}> */
        return is_array($flashes) ? array_values($flashes) : [];
    }
}
