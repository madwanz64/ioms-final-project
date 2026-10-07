<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Parameter pencarian daftar order PO/SO (FIND-01): nomor/pihak terkait,
 * filter status, sort tanggal naik/turun, pagination 10 per halaman.
 */
final class OrderSearchCriteria
{
    public const PER_PAGE = 10;
    public const SORTS = ['date_desc', 'date_asc'];

    public function __construct(
        public readonly string $search = '',
        public readonly string $status = '',
        public readonly string $sort = 'date_desc',
        public readonly int $page = 1,
        public readonly int $perPage = self::PER_PAGE,
    ) {
    }

    /**
     * @param array<string, string> $query query string mentah (q, status, sort, page)
     * @param list<string> $allowedStatuses status yang valid untuk jenis order ini
     */
    public static function fromQuery(array $query, array $allowedStatuses): self
    {
        $status = $query['status'] ?? '';
        $sort = $query['sort'] ?? '';
        $page = $query['page'] ?? '';

        return new self(
            search: mb_substr($query['q'] ?? '', 0, 100),
            status: in_array($status, $allowedStatuses, true) ? $status : '',
            sort: in_array($sort, self::SORTS, true) ? $sort : 'date_desc',
            page: ctype_digit($page) && (int) $page > 0 ? (int) $page : 1,
        );
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->status !== '';
    }

    /**
     * Query string aktif untuk link pagination (filter tetap terbawa).
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter(['q' => $this->search, 'status' => $this->status, 'sort' => $this->sort], static fn (string $v): bool => $v !== '');
    }
}
