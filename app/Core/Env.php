<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loader .env minimal (tanpa library): format KEY=VALUE per baris.
 * Environment variable sungguhan (mis. dari Docker Compose) selalu menang
 * atas isi file, sehingga konfigurasi container tidak tertimpa .env lokal.
 */
final class Env
{
    /**
     * @return array<string, string>
     */
    public static function load(string $path): array
    {
        $values = is_file($path) ? self::parse((string) file_get_contents($path)) : [];

        foreach (array_keys($values) as $key) {
            $real = getenv($key);
            if ($real !== false) {
                $values[$key] = $real;
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }
}
