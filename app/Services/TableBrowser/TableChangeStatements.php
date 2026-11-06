<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\Identifier;
use App\Services\TableBrowser\Data\TableChange;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * The statement of each change, compiled by the grammar of the database: shown to the user before being applied,
 * then run as is, so what was reviewed is exactly what is executed.
 */
class TableChangeStatements
{
    /**
     * @return array{sql: string, bindings: list<mixed>}
     */
    public function compile(DriverContract $driver, string $table, TableChange $change): array
    {
        $query = $this->rowQuery($driver, $table, $change->key);
        $grammar = $query->getGrammar();

        return match ($change->type) {
            'insert' => [
                'sql' => $grammar->compileInsert($query, [$values = $this->sorted($change->values)]),
                'bindings' => array_values($values),
            ],
            'update' => [
                'sql' => $grammar->compileUpdate($query, $change->values),
                'bindings' => array_values($grammar->prepareBindingsForUpdate($query->getRawBindings(), $change->values)),
            ],
            'delete' => [
                'sql' => $grammar->compileDelete($query),
                'bindings' => array_values($grammar->prepareBindingsForDelete($query->getRawBindings())),
            ],
        };
    }

    /**
     * The statement with its values written in, to be read by the user.
     */
    public function readable(DriverContract $driver, string $table, TableChange $change): string
    {
        ['sql' => $sql, 'bindings' => $bindings] = $this->compile($driver, $table, $change);
        $connection = $driver->getConnection();

        return $connection->getQueryGrammar()->substituteBindingsIntoRawSql($sql, $connection->prepareBindings($bindings)).';';
    }

    /**
     * The query on the row with the given primary key, or on the whole table without one.
     *
     * @param  array<string, mixed>  $key
     */
    public function rowQuery(DriverContract $driver, string $table, array $key): Builder
    {
        $connection = $driver->getConnection();
        $query = $connection->table(new Expression(Identifier::quote($connection, $table)));

        foreach ($key as $column => $value) {
            $query->where(new Expression(Identifier::quote($connection, $column)), '=', $value);
        }

        return $query;
    }

    /**
     * The values of an insert in the order of their columns, as the query builder sends them.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
