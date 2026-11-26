<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Contracts\DriverContract;
use App\Services\TableBrowser\TableCatalog;
use Closure;

/**
 * Move a column of a table to another position, its definition, its rows and everything attached to the table kept.
 */
class ColumnMover
{
    public function __construct(
        protected TableCatalog $catalog,
        protected TableDesignApplier $applier,
        protected ColumnPositioner $positioner,
    ) {}

    /**
     * The statements that would move the column, none when it is already there.
     *
     * @param  ?string  $after  the column it follows, null to make it the first one
     * @return list<string>
     *
     * @throws TableDesignFailedException
     */
    public function preview(DriverContract $driver, string $table, string $column, ?string $after): array
    {
        return $this->phase($driver, $table, $column, $after)();
    }

    /**
     * @param  ?string  $after  the column it follows, null to make it the first one
     * @return list<string> the statements that ran
     *
     * @throws TableDesignFailedException
     */
    public function move(DriverContract $driver, string $table, string $column, ?string $after): array
    {
        return $this->applier->applyAtomically($driver, [$this->phase($driver, $table, $column, $after)]);
    }

    /**
     * @return Closure(): list<string>
     *
     * @throws TableDesignFailedException
     */
    protected function phase(DriverContract $driver, string $table, string $column, ?string $after): Closure
    {
        $this->catalog->definition($driver, $table);
        $connection = $driver->getConnection();

        if (! $this->positioner->supports($connection)) {
            throw new TableDesignFailedException('', __('tables.errors.column_move_unsupported'));
        }

        return function () use ($connection, $table, $column, $after): array {
            $columns = array_column($connection->getSchemaBuilder()->getColumns($table), 'name');

            foreach (array_filter([$column, $after]) as $name) {
                if (! in_array($name, $columns, true)) {
                    throw new TableDesignFailedException('', __('tables.errors.unknown_column', ['column' => $name, 'table' => $table]));
                }
            }

            $order = $this->reorder($columns, $column, $after);

            if ($order === $columns) {
                return [];
            }

            if ($connection->getDriverName() === 'sqlite') {
                return $this->positioner->rebuildSqliteTable($connection, $table, $order);
            }

            $definition = $this->positioner->mysqlDefinitions($connection, $table)[$column]
                ?? throw new TableDesignFailedException('', __('tables.errors.column_move_unreadable'));

            return [$this->positioner->mysqlMove($connection, $table, $column, $definition, $after)];
        };
    }

    /**
     * The columns of the table once the column is moved.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    protected function reorder(array $columns, string $column, ?string $after): array
    {
        if ($column === $after) {
            return $columns;
        }

        $others = array_values(array_diff($columns, [$column]));
        $position = $after === null ? 0 : array_search($after, $others, true) + 1;
        array_splice($others, $position, 0, [$column]);

        return $others;
    }
}
