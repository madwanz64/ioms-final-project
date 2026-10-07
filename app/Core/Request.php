<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = rtrim($path, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === '' ? '/' : $path,
            $_GET,
            $_POST,
            $_FILES,
        );
    }

    public function query(string $key, string $default = ''): string
    {
        return self::stringValue($this->query[$key] ?? null) ?? $default;
    }

    public function input(string $key, string $default = ''): string
    {
        return self::stringValue($this->body[$key] ?? null) ?? $default;
    }

    /**
     * Seluruh query string sebagai string ter-trim (nilai array diabaikan).
     *
     * @return array<string, string>
     */
    public function allQuery(): array
    {
        return self::stringsOnly($this->query);
    }

    /**
     * @return array<string, string>
     */
    public function allInput(): array
    {
        return self::stringsOnly($this->body);
    }

    /**
     * Baris berulang dari form, mis. items[0][sku], items[0][qty], ...
     * Nilai non-string diabaikan; urutan baris dipertahankan.
     *
     * @return list<array<string, string>>
     */
    public function inputRows(string $key): array
    {
        $rows = $this->body[$key] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = self::stringsOnly($row);
            }
        }

        return $result;
    }

    /**
     * Peta satu tingkat dari form, mis. receive[12]=5 -> [12 => '5'].
     * PHP mengubah key numerik menjadi int, jadi key bisa int atau string.
     *
     * @return array<array-key, string>
     */
    public function inputMap(string $key): array
    {
        $map = $this->body[$key] ?? null;
        if (!is_array($map)) {
            return [];
        }

        return array_filter(array_map(static fn (mixed $v): ?string => is_string($v) ? trim($v) : null, $map), 'is_string');
    }

    /**
     * File yang diunggah, atau null bila field tidak diisi sama sekali.
     */
    public function file(string $key): ?UploadedFile
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !is_string($file['tmp_name'] ?? null)) {
            return null;
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new UploadedFile($file['tmp_name'], (string) ($file['name'] ?? ''), (int) ($file['size'] ?? 0), $error);
    }

    private static function stringValue(mixed $value): ?string
    {
        return is_string($value) ? trim($value) : null;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, string>
     */
    private static function stringsOnly(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $result[(string) $key] = trim($value);
            }
        }

        return $result;
    }
}
