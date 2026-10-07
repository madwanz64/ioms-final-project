<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderSummary;

/**
 * Konversi baris hasil PDO yang dipakai beberapa repository MySQL (refactor R-03).
 * Hanya fungsi murni tanpa state — bukan lapisan baru, sekadar satu tempat untuk
 * aturan konversi yang sebelumnya tersalin di banyak kelas.
 */
final class RowMapper
{
    /**
     * Kolom harga/total DECIMAL(14,2) dikembalikan PDO sebagai string ("45000.00");
     * aplikasi memakai rupiah utuh (int).
     */
    public static function money(mixed $value): int
    {
        return (int) round((float) $value);
    }

    /**
     * Baris daftar/antrean order PO & SO. Kolom wajib: id, order_no, party_name,
     * warehouse_name, status, order_date, item_count, total.
     *
     * @param array<string, mixed> $row
     */
    public static function orderSummary(array $row): OrderSummary
    {
        return new OrderSummary(
            (int) $row['id'],
            (string) $row['order_no'],
            (string) $row['party_name'],
            (string) $row['warehouse_name'],
            (string) $row['status'],
            (string) $row['order_date'],
            (int) $row['item_count'],
            self::money($row['total']),
        );
    }
}
