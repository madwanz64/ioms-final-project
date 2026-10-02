<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Parameter pencarian produk (FIND-01). Nilai sort & status stok di-whitelist
 * di sini, sehingga repository tidak pernah menerima string bebas dari user
 * untuk bagian query yang tidak bisa di-bind (ORDER BY).
 */
final class ProductSearchCriteria
{
    public const PER_PAGE = 10;
    public const SORTS = ['name_asc', 'name_desc', 'stock_asc'];
    public const STOCK_STATUSES = ['', 'low', 'normal'];

    public function __construct(
        public readonly string $search = '',
        public readonly ?int $categoryId = null,
        public readonly string $stockStatus = '',
        public readonly string $sort = 'name_asc',
        public readonly int $page = 1,
        public readonly int $perPage = self::PER_PAGE,
        public readonly bool $onlyActive = false,
    ) {
    }

    /**
     * @param array<string, string> $query query string mentah (q, category, stock, sort, page)
     */
    public static function fromQuery(array $query): self
    {
        $category = $query['category'] ?? '';
        $stock = $query['stock'] ?? '';
        $sort = $query['sort'] ?? '';
        $page = $query['page'] ?? '';

        return new self(
            search: mb_substr($query['q'] ?? '', 0, 100),
            categoryId: ctype_digit($category) && (int) $category > 0 ? (int) $category : null,
            stockStatus: in_array($stock, self::STOCK_STATUSES, true) ? $stock : '',
            sort: in_array($sort, self::SORTS, true) ? $sort : 'name_asc',
            page: ctype_digit($page) && (int) $page > 0 ? (int) $page : 1,
        );
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->categoryId !== null || $this->stockStatus !== '';
    }

    public function withPage(int $page): self
    {
        return new self($this->search, $this->categoryId, $this->stockStatus, $this->sort, $page, $this->perPage, $this->onlyActive);
    }

    public function withOnlyActive(): self
    {
        return new self($this->search, $this->categoryId, $this->stockStatus, $this->sort, $this->page, $this->perPage, true);
    }
}
