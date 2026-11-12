<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\Identifier;
use App\Services\TableBrowser\Data\TableView;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use RuntimeException;

/**
 * The SELECT of the rows of a table matching a view. Columns, operators and directions come from lists checked
 * against the schema, and values are always bound: nothing written by the user reaches the SQL as is.
 */
class TableQuery
{
    public function __construct(protected TableCatalog $catalog) {}

    /**
     * @param  bool  $sorted  false for a count, where an ORDER BY is useless and refused by PostgreSQL
     *
     * @throws RuntimeException when a filter or a sort names a column the table does not have.
     */
    public function build(DriverContract $driver, string $table, TableView $view, bool $sorted = true): Builder
    {
        $definition = $this->catalog->definition($driver, $table);
        $columns = array_column($definition['columns'], 'name');
        $connection = $driver->getConnection();
        $column = fn (string $name): Expression => new Expression(Identifier::quote($connection, $this->assertColumn($name, $columns, $table)));

        $query = $connection->table(new Expression(Identifier::quote($connection, $table)));

        if ($view->filters !== []) {
            $query->where(function (Builder $query) use ($view, $column): void {
                foreach ($view->filters as $filter) {
                    $filter->operator->apply($query, $column($filter->column), $filter->value, $view->match === 'any' ? 'or' : 'and');
                }
            });
        }

        if (! $sorted) {
            return $query;
        }

        foreach ($view->sorts as $sort) {
            $query->orderBy($column($sort['column']), $sort['direction']);
        }

        // Without an order the database may return the rows of two pages in any order: the primary key keeps them stable.
        foreach ($view->sorts === [] ? $definition['primary_key'] : [] as $primaryColumn) {
            $query->orderBy($column($primaryColumn));
        }

        return $query;
    }

    /**
     * @param  list<string>  $columns
     *
     * @throws RuntimeException
     */
    protected function assertColumn(string $name, array $columns, string $table): string
    {
        return in_array($name, $columns, true)
            ? $name
            : throw new RuntimeException(__('tables.errors.unknown_column', ['column' => $name, 'table' => $table]));
    }
}
