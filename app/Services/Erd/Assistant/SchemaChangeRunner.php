<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\Identifier;
use App\Services\TableDesigner\TableBlueprintBuilder;
use App\Services\TableDesigner\TableDesignApplier;
use App\Services\TableDesigner\TableDesignFailedException;
use Closure;

/**
 * Show or run the changes of a plan with the designer of tables, one after the other.
 *
 * PostgreSQL makes them all in one transaction: a refused statement undoes the others. MySQL and MariaDB commit each
 * statement at once, and SQLite rebuilds a table outside a transaction: the changes made before a refused one stay.
 */
class SchemaChangeRunner
{
    public function __construct(
        protected TableBlueprintBuilder $builder,
        protected TableDesignApplier $applier,
    ) {}

    /**
     * Every statement the plan would run.
     *
     * @return list<string>
     *
     * @throws TableDesignFailedException
     */
    public function preview(DriverContract $driver, SchemaChangePlan $plan): array
    {
        return $this->applier->preview($driver, array_merge(...array_column($this->steps($driver, $plan), 'phases')));
    }

    /**
     * @return list<string> the statements that ran
     *
     * @throws SchemaChangeFailedException
     */
    public function apply(DriverContract $driver, SchemaChangePlan $plan): array
    {
        $connection = $driver->getConnection();
        $applied = [];
        $run = function () use ($driver, $plan, &$applied): array {
            $statements = [];

            foreach ($this->steps($driver, $plan) as $step) {
                $statements = [...$statements, ...$this->applier->apply($driver, $step['phases'])];
                $applied[] = $step['change'];
            }

            return $statements;
        };

        try {
            return $connection->getDriverName() === 'pgsql' ? $connection->transaction($run) : $run();
        } catch (TableDesignFailedException $exception) {
            throw new SchemaChangeFailedException($exception, $connection->getDriverName() === 'pgsql' ? [] : $applied);
        } finally {
            $driver->forgetSchema();
        }
    }

    /**
     * The phases of each change in the order they run; the keys of the tables created in a cycle are added once
     * every table is created.
     *
     * @return list<array{change: array{kind: string, table: ?string}, phases: list<Closure(): list<string>>}>
     */
    protected function steps(DriverContract $driver, SchemaChangePlan $plan): array
    {
        $steps = [];
        $deferred = [];

        foreach ($plan->changes as $change) {
            if ($change->kind !== SchemaChange::CREATE && $deferred !== []) {
                array_push($steps, ...$deferred);
                $deferred = [];
            }

            $steps[] = ['change' => ['kind' => $change->kind, 'table' => $change->table], 'phases' => $this->phases($driver, $change)];

            if ($change->kind === SchemaChange::CREATE && $change->deferredForeignKeys !== []) {
                $deferred[] = ['change' => ['kind' => SchemaChange::ALTER, 'table' => $change->table], 'phases' => $this->deferredForeignKeys($driver, $change)];
            }
        }

        return [...$steps, ...$deferred];
    }

    /**
     * @return list<Closure(): list<string>>
     */
    protected function phases(DriverContract $driver, SchemaChange $change): array
    {
        $connection = $driver->getConnection();

        return match ($change->kind) {
            SchemaChange::CREATE => $this->builder->create($connection, $change->design),
            SchemaChange::ALTER => $this->builder->alter($connection, $change->current, $change->design),
            SchemaChange::DROP => [fn (): array => ['drop table '.Identifier::quote($connection, (string) $change->from)]],
            SchemaChange::RAW => [fn (): array => [(string) $change->statement]],
        };
    }

    /**
     * The keys of a table created in a cycle, added once the tables they reference exist: the table is changed
     * from the design it was just created with, its columns, indexes and other keys left as they are.
     *
     * @return list<Closure(): list<string>>
     */
    protected function deferredForeignKeys(DriverContract $driver, SchemaChange $change): array
    {
        if ($change->deferredForeignKeys === []) {
            return [];
        }

        $columns = array_map(fn (array $column): array => [...$column, 'original' => $column['name'], 'changed' => false], $change->design['columns']);
        $current = [...$change->design, 'columns' => $columns, 'indexes' => [], 'foreignKeys' => [], 'primaryKeyName' => null];

        return $this->builder->alter($driver->getConnection(), $current, [...$current, 'foreignKeys' => $change->deferredForeignKeys]);
    }
}
