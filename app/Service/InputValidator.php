<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;

/**
 * Pengumpul hasil validasi untuk satu form (VAL-01).
 *
 * Setiap method memeriksa satu field, mencatat pesan error bila tidak valid,
 * dan mengembalikan nilai yang sudah dinormalisasi (trim / int). Nilai yang
 * dikembalikan hanya boleh dipakai setelah throwIfInvalid() lolos.
 * Semua error dikumpulkan dulu supaya user melihat semuanya sekaligus.
 */
final class InputValidator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, string> $input
     */
    public function __construct(private readonly array $input)
    {
    }

    public function requiredText(string $field, string $label, int $maxLength): string
    {
        $value = $this->raw($field);
        if ($value === '') {
            $this->addError($field, $label . ' wajib diisi.');
        } elseif (mb_strlen($value) > $maxLength) {
            $this->addError($field, sprintf('%s maksimal %d karakter.', $label, $maxLength));
        }

        return $value;
    }

    public function optionalText(string $field, string $label, int $maxLength): ?string
    {
        $value = $this->raw($field);
        if (mb_strlen($value) > $maxLength) {
            $this->addError($field, sprintf('%s maksimal %d karakter.', $label, $maxLength));
        }

        return $value === '' ? null : $value;
    }

    /**
     * Bilangan bulat >= 0. ctype_digit menolak "-5", "1.5", "1e3", dan spasi.
     */
    public function wholeNumber(string $field, string $label, int $max): int
    {
        $value = $this->raw($field);
        if ($value === '') {
            $this->addError($field, $label . ' wajib diisi.');

            return 0;
        }
        if (!ctype_digit($value)) {
            $this->addError($field, $label . ' harus bilangan bulat >= 0.');

            return 0;
        }
        // Cek panjang dulu agar string angka raksasa tidak overflow saat di-cast ke int.
        if (strlen(ltrim($value, '0')) > strlen((string) $max) || (int) $value > $max) {
            $this->addError($field, sprintf('%s maksimal %s.', $label, number_format($max, 0, ',', '.')));

            return 0;
        }

        return (int) $value;
    }

    /**
     * Status aktif dari <select> bernilai "1"/"0".
     */
    public function activeFlag(string $field = 'active'): bool
    {
        $value = $this->raw($field);
        if (!in_array($value, ['0', '1'], true)) {
            $this->addError($field, 'Status tidak valid.');
        }

        return $value === '1';
    }

    /**
     * ID numerik positif (mis. foreign key dari <select>). Keberadaan datanya
     * diperiksa pemanggil lewat repository.
     */
    public function positiveId(string $field): ?int
    {
        $value = $this->raw($field);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Tanggal format YYYY-MM-DD yang benar-benar ada di kalender (2026-02-30
     * ditolak) dan tidak melewati $today.
     */
    public function dateNotAfter(string $field, string $label, DateTimeImmutable $today): string
    {
        $value = $this->raw($field);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($value === '') {
            $this->addError($field, $label . ' wajib diisi.');
        } elseif ($date === false || $date->format('Y-m-d') !== $value) {
            $this->addError($field, $label . ' tidak valid (format YYYY-MM-DD).');
        } elseif ($date > $today) {
            $this->addError($field, $label . ' tidak boleh di masa depan.');
        }

        return $value;
    }

    public function raw(string $field): string
    {
        return trim($this->input[$field] ?? '');
    }

    public function addError(string $field, string $message): void
    {
        // Pesan pertama untuk sebuah field yang dipertahankan.
        $this->errors[$field] ??= $message;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @throws ValidationException
     */
    public function throwIfInvalid(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }
}
