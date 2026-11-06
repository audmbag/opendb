<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\Identifier;
use App\Services\TableBrowser\TableCatalog;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Throwable;

/**
 * What can be done to a table as a whole: rename it, empty it, drop it or copy it under another name.
 */
class TableOperations
{
    public function __construct(
        protected TableCatalog $catalog,
        protected TableDesignReader $reader,
        protected TableBlueprintBuilder $builder,
        protected TableDesignApplier $applier,
    ) {}

    /**
     * @throws TableDesignFailedException
     */
    public function rename(DriverContract $driver, string $table, string $name): void
    {
        $this->catalog->definition($driver, $table);
        $connection = $driver->getConnection();

        $connection->getSchemaBuilder();

        $this->applier->apply($driver, [fn (): array => new Blueprint($connection, $table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->rename($name);
        })->toSql()]);
    }

    /**
     * Delete every row and start the identifiers again from the first. A table referenced by another one is refused
     * by MySQL and PostgreSQL rather than emptied with the rows referencing it.
     *
     * @return int the number of rows deleted
     *
     * @throws TableDesignFailedException
     */
    public function truncate(DriverContract $driver, string $table): int
    {
        $this->catalog->definition($driver, $table);
        $connection = $driver->getConnection();
        $quoted = Identifier::quote($connection, $table);
        $count = $connection->table($table)->count();

        $statement = match ($connection->getDriverName()) {
            'pgsql' => "truncate table {$quoted} restart identity",
            'sqlite' => "delete from {$quoted}",
            default => "truncate table {$quoted}",
        };

        $this->statement($connection, $statement);

        if ($connection->getDriverName() === 'sqlite') {
            try {
                $connection->table('sqlite_sequence')->where('name', $table)->delete();
            } catch (Throwable) {
                // Only tables with an AUTOINCREMENT column have a sequence.
            }
        }

        return $count;
    }

    /**
     * @throws TableDesignFailedException
     */
    public function drop(DriverContract $driver, string $table): void
    {
        $this->catalog->definition($driver, $table);
        $connection = $driver->getConnection();

        try {
            $this->statement($connection, 'drop table '.Identifier::quote($connection, $table));
        } finally {
            $driver->forgetSchema();
        }
    }

    /**
     * A new table with the same columns, keys and indexes, and the rows of the original one when asked for.
     * Indexes and foreign keys take the default names of the new table, as their names are unique in a schema.
     *
     * @throws TableDesignFailedException
     */
    public function duplicate(DriverContract $driver, string $table, string $name, bool $withRows): void
    {
        $design = $this->reader->read($driver, $table);
        $connection = $driver->getConnection();
        $quote = fn (string $identifier): string => Identifier::quote($connection, $identifier);

        $copy = [
            ...$design,
            'name' => $name,
            'columns' => array_map(fn (array $column): array => [...$column, 'original' => null, 'type' => $column['type'] === 'custom' ? 'text' : $column['type']], $design['columns']),
            'indexes' => array_map(fn (array $index): array => [...$index, 'original' => null, 'name' => null], $design['indexes']),
            'foreignKeys' => array_map(fn (array $foreignKey): array => [...$foreignKey, 'original' => null, 'name' => null], $design['foreignKeys']),
        ];

        $columns = implode(', ', array_map($quote, array_column($design['columns'], 'name')));
        $phases = $this->builder->create($connection, $copy);

        if ($withRows) {
            $phases[] = fn (): array => ["insert into {$quote($name)} ({$columns}) select {$columns} from {$quote($table)}"];
        }

        $this->applier->apply($driver, $phases);
    }

    /**
     * @throws TableDesignFailedException
     */
    protected function statement(Connection $connection, string $statement): void
    {
        try {
            $connection->statement($statement);
        } catch (QueryException $exception) {
            throw new TableDesignFailedException($statement, (string) ($exception->errorInfo[2] ?? $exception->getMessage()), $exception);
        }
    }
}
