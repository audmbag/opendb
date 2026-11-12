<?php

namespace App\Database;

use App\Database\Concerns\QuotesIdentifiers;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\TableNotFoundException;
use RuntimeException;

/**
 * A row of a table with the rows it references and the number of rows referencing it, to browse its relations.
 */
class RecordInspector
{
    use QuotesIdentifiers;

    public const int MAX_INCOMING_RELATIONS = 20;

    /**
     * @var array{name: string, columns: list<array<string, mixed>>, primary_key: list<string>, foreign_keys: list<array{columns: list<string>, references_table: string, references_columns: list<string>}>}
     */
    protected array $definition;

    /**
     * @var list<array<string, mixed>>
     */
    protected array $schema;

    /**
     * @throws HiddenTableException when the table is hidden from the agent.
     * @throws TableNotFoundException when the table is missing.
     */
    public function __construct(
        protected DriverContract $driver,
        public readonly string $table,
    ) {
        HiddenTableGuard::assertTableAllowed($table, $driver->hiddenTables());

        $this->schema = $driver->getSchema();
        $definition = collect($this->schema)->firstWhere('name', $table);

        if ($definition === null || ! in_array($table, $driver->listTables(false), true)) {
            throw TableNotFoundException::named($table);
        }

        $this->definition = $definition;
    }

    /**
     * The row identified by the given values, usually those of its primary key.
     *
     * @param  array<string, mixed>  $key
     * @return array{table: string, primaryKey: list<string>, key: array<string, mixed>, values: array<string, mixed>, outgoing: list<array{columns: list<string>, table: string, key: array<string, mixed>}>, incoming: list<array{table: string, columns: list<string>, key: array<string, mixed>, count: int}>}
     *
     * @throws RuntimeException when the key does not name columns of the table or no row matches it.
     */
    public function find(array $key): array
    {
        $columns = array_column($this->definition['columns'], 'name');

        if ($key === [] || array_diff(array_keys($key), $columns) !== []) {
            throw new RuntimeException(__('database.records.invalid_key', ['table' => $this->table, 'columns' => implode(', ', $columns)]));
        }

        $row = $this->rows($this->table, $key, 1)[0] ?? null;

        if ($row === null) {
            throw new RuntimeException(__('database.records.not_found', ['table' => $this->table, 'key' => (string) json_encode($key, JSON_UNESCAPED_UNICODE)]));
        }

        return [
            'table' => $this->table,
            'primaryKey' => $this->definition['primary_key'],
            'key' => $key,
            'values' => array_map(SelectQuery::displayableValue(...), $row),
            'outgoing' => $this->outgoing($row),
            'incoming' => $this->incoming($row),
        ];
    }

    /**
     * The rows referenced by the foreign keys of the row.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{columns: list<string>, table: string, key: array<string, mixed>}>
     */
    protected function outgoing(array $row): array
    {
        return collect($this->definition['foreign_keys'])
            ->map(fn (array $foreignKey): array => [
                'columns' => $foreignKey['columns'],
                'table' => $foreignKey['references_table'],
                'key' => array_combine($foreignKey['references_columns'], array_map(fn (string $column) => $row[$column] ?? null, $foreignKey['columns'])),
            ])
            ->reject(fn (array $relation): bool => in_array(null, $relation['key'], true))
            ->values()
            ->all();
    }

    /**
     * The number of rows of each table whose foreign key references the row.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{table: string, columns: list<string>, key: array<string, mixed>, count: int}>
     */
    protected function incoming(array $row): array
    {
        $visibleTables = $this->driver->listTables(false);

        return collect($this->schema)
            ->filter(fn (array $table): bool => in_array($table['name'], $visibleTables, true))
            ->flatMap(fn (array $table) => collect($table['foreign_keys'] ?? [])
                ->filter(fn (array $foreignKey): bool => $foreignKey['references_table'] === $this->table)
                ->map(fn (array $foreignKey): array => [
                    'table' => $table['name'],
                    'columns' => $foreignKey['columns'],
                    'key' => array_combine($foreignKey['columns'], array_map(fn (string $column) => $row[$column] ?? null, $foreignKey['references_columns'])),
                ]))
            ->reject(fn (array $relation): bool => in_array(null, $relation['key'], true))
            ->take(self::MAX_INCOMING_RELATIONS)
            ->map(fn (array $relation): array => [...$relation, 'count' => $this->count($relation['table'], $relation['key'])])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $key
     * @return list<array<string, mixed>>
     */
    protected function rows(string $table, array $key, int $limit): array
    {
        [$where, $bindings] = $this->where($key);

        return array_map(
            fn (object $row): array => (array) $row,
            $this->driver->executeReadOnly('SELECT * FROM '.$this->quoteIdentifier($table)." WHERE {$where} LIMIT {$limit}", $bindings),
        );
    }

    /**
     * @param  array<string, mixed>  $key
     */
    protected function count(string $table, array $key): int
    {
        [$where, $bindings] = $this->where($key);

        return (int) ($this->driver->executeReadOnly('SELECT COUNT(*) AS aggregate FROM '.$this->quoteIdentifier($table)." WHERE {$where}", $bindings)[0]->aggregate ?? 0);
    }

    /**
     * @param  array<string, mixed>  $key
     * @return array{0: string, 1: list<mixed>}
     */
    protected function where(array $key): array
    {
        $conditions = array_map(fn (string $column): string => $this->quoteIdentifier($column).' = ?', array_keys($key));

        return [implode(' AND ', $conditions), array_values($key)];
    }
}
