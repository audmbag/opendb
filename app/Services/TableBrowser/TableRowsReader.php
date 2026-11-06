<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\SelectQuery;
use App\Services\TableBrowser\Data\TableView;
use Generator;

/**
 * The rows of a table shown by the grid, one page at a time, and every row of the view for an export.
 */
class TableRowsReader
{
    public const int CHUNK_SIZE = 1000;

    public function __construct(protected TableQuery $query) {}

    /**
     * One more row than the page is read to know whether another page follows, without counting the whole table.
     *
     * The statement is returned with its values written in, as the user would type it to read the same page.
     *
     * @return array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool, sql: string}
     */
    public function page(DriverContract $driver, string $table, TableView $view): array
    {
        $query = $this->query->build($driver, $table, $view)->offset($view->offset())->limit($view->perPage);
        $rows = $this->rows((clone $query)->limit($view->perPage + 1)->get()->all());

        return [
            'rows' => array_slice($rows, 0, $view->perPage),
            'page' => $view->page,
            'perPage' => $view->perPage,
            'hasMore' => count($rows) > $view->perPage,
            'sql' => $query->toRawSql(),
        ];
    }

    /**
     * Every row of the view in chunks, in a stable order so that no row is read twice or skipped.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function chunks(DriverContract $driver, string $table, TableView $view): Generator
    {
        $query = $this->query->build($driver, $table, $view);
        $offset = 0;

        do {
            $rows = $this->rows((clone $query)->offset($offset)->limit(self::CHUNK_SIZE)->get()->all());

            if ($rows !== []) {
                yield $rows;
            }

            $offset += self::CHUNK_SIZE;
        } while (count($rows) === self::CHUNK_SIZE);
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function rows(array $rows): array
    {
        return array_map(fn (object $row): array => array_map(SelectQuery::displayableValue(...), (array) $row), $rows);
    }
}
