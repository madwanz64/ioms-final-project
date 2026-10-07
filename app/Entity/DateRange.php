<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Rentang tanggal inklusif (dari 00:00 tanggal awal s.d. 23:59:59 tanggal akhir).
 */
final class DateRange
{
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {
        if ($from > $to) {
            throw new InvalidArgumentException('Tanggal awal tidak boleh setelah tanggal akhir.');
        }
    }

    public function fromDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function toDate(): string
    {
        return $this->to->format('Y-m-d');
    }

    /**
     * Batas atas eksklusif untuk kolom TIMESTAMP: created_at < (to + 1 hari).
     */
    public function toExclusiveDateTime(): string
    {
        return $this->to->modify('+1 day')->format('Y-m-d 00:00:00');
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }
}
