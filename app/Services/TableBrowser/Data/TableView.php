<?php

declare(strict_types=1);

namespace App\Services\TableBrowser\Data;

/**
 * What the user looks at in a table: the rows matching the filters, in the chosen order, one page at a time.
 */
final readonly class TableView
{
    public const int DEFAULT_PER_PAGE = 100;

    /**
     * @param  list<TableFilter>  $filters
     * @param  'all'|'any'  $match  whether a row matches every filter or at least one of them
     * @param  list<array{column: string, direction: 'asc'|'desc'}>  $sorts
     */
    public function __construct(
        public array $filters = [],
        public string $match = 'all',
        public array $sorts = [],
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * @param  array{filters?: list<array{column: string, operator: string, value?: mixed}>, match?: 'all'|'any', sorts?: list<array{column: string, direction?: 'asc'|'desc'}>, page?: int|string, perPage?: int|string}  $view
     */
    public static function fromArray(array $view): self
    {
        return new self(
            filters: array_map(TableFilter::fromArray(...), array_values($view['filters'] ?? [])),
            match: ($view['match'] ?? 'all') === 'any' ? 'any' : 'all',
            sorts: array_map(fn (array $sort): array => [
                'column' => $sort['column'],
                'direction' => ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            ], array_values($view['sorts'] ?? [])),
            page: max(1, (int) ($view['page'] ?? 1)),
            perPage: (int) ($view['perPage'] ?? self::DEFAULT_PER_PAGE),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
