<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Respons kontrak API (API-01): selalu application/json, termasuk untuk error.
     *
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    /**
     * File CSV untuk diunduh (UTF-8 dengan BOM agar Excel membaca karakter Indonesia dengan benar).
     *
     * @param list<list<string|int>> $rows
     */
    public static function csv(string $filename, array $rows): self
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InfrastructureException('Tidak dapat membuat buffer CSV.');
        }
        foreach ($rows as $row) {
            fputcsv($handle, array_map([self::class, 'csvCell'], $row), ',', '"', '');
        }
        rewind($handle);
        $body = "\xEF\xBB\xBF" . stream_get_contents($handle);
        fclose($handle);

        return new self($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Cegah CSV/formula injection: teks yang diawali = + - @ (atau tab/CR) akan
     * dieksekusi sebagai rumus oleh Excel, jadi diawali tanda kutip tunggal.
     * Angka (termasuk negatif seperti qty -4) dibiarkan apa adanya.
     */
    public static function csvCell(string|int $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    public static function redirect(string $location): self
    {
        return new self('', 302, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
