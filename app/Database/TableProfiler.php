<?php

namespace App\Database;

use App\Database\Concerns\QuotesIdentifiers;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\Schema\SnapshotBuilder;

/**
 * The profile of the data held by a table: how each column is filled, computed on a sample of its rows.
 */
class TableProfiler
{
    use QuotesIdentifiers;

    public const int SAMPLE_SIZE = 10000;

    public const int TOP_VALUES = 5;

    /**
     * @throws HiddenTableException when the table is hidden from the agent.
     * @throws TableNotFoundException when the table is missing.
     */
    public function __construct(
        protected DriverContract $driver,
        public readonly string $table,
    ) {
        HiddenTableGuard::assertTableAllowed($table, $driver->hiddenTables());

        if (! in_array($table, $driver->listTables(false), true)) {
            throw TableNotFoundException::named($table);
        }
    }

    /**
     * @return array{table: string, totalRows: int, sampleSize: int, columns: list<array{name: string, type: string, key: ?string, kind: string, nullCount: int, nullRatio: float, distinctCount: ?int, min: mixed, max: mixed, averageLength: ?float, minLength: ?int, maxLength: ?int, topValues: list<array{value: mixed, count: int}>}>}
     */
    public function profile(): array
    {
        $totalRows = (int) ($this->driver->executeReadOnly('SELECT COUNT(*) AS aggregate FROM '.$this->quoteIdentifier($this->table))[0]->aggregate ?? 0);
        $sample = '(SELECT * FROM '.$this->quoteIdentifier($this->table).' LIMIT '.self::SAMPLE_SIZE.') AS opendb_sample';
        $sampleSize = min($totalRows, self::SAMPLE_SIZE);

        ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys] = app(SnapshotBuilder::class)->describe($this->driver->getConnection(), $this->table);
        $keys = $this->keys($indexes, $foreignKeys);
        $kinds = array_map(fn (array $column): string => $this->kind($column), $columns);
        $statistics = $this->statistics($columns, $kinds, $sample);

        return [
            'table' => $this->table,
            'totalRows' => $totalRows,
            'sampleSize' => $sampleSize,
            'columns' => array_map(
                fn (array $column, string $kind, array $stats): array => $this->profileColumn($column, $kind, $keys[$column['name']] ?? null, $stats, $sample, $sampleSize),
                $columns,
                $kinds,
                $statistics,
            ),
        ];
    }

    /**
     * The aggregates of every column, computed by a single query so the sample is read once instead of once per column.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $kinds
     * @return list<array{filled: int, distinct: ?int, min: mixed, max: mixed, averageLength: ?float, minLength: ?int, maxLength: ?int}>
     */
    protected function statistics(array $columns, array $kinds, string $sample): array
    {
        $length = $this->driver->getConnection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        $aggregates = [];

        foreach ($columns as $index => $column) {
            $name = $this->quoteIdentifier($column['name']);
            $kind = $kinds[$index];

            $aggregates[] = "COUNT({$name}) AS c{$index}_filled";

            if ($kind !== 'other') {
                $aggregates[] = "COUNT(DISTINCT {$name}) AS c{$index}_distinct";
            }

            if (in_array($kind, ['numeric', 'temporal'], true)) {
                $aggregates[] = "MIN({$name}) AS c{$index}_min, MAX({$name}) AS c{$index}_max";
            }

            if ($kind === 'text') {
                $aggregates[] = "AVG({$length}({$name})) AS c{$index}_average_length, MIN({$length}({$name})) AS c{$index}_min_length, MAX({$length}({$name})) AS c{$index}_max_length";
            }
        }

        $row = (array) $this->driver->executeReadOnly('SELECT '.implode(', ', $aggregates)." FROM {$sample}")[0];

        return array_map(fn (int $index): array => [
            'filled' => (int) $row["c{$index}_filled"],
            'distinct' => isset($row["c{$index}_distinct"]) ? (int) $row["c{$index}_distinct"] : null,
            'min' => $row["c{$index}_min"] ?? null,
            'max' => $row["c{$index}_max"] ?? null,
            'averageLength' => isset($row["c{$index}_average_length"]) ? round((float) $row["c{$index}_average_length"], 1) : null,
            'minLength' => isset($row["c{$index}_min_length"]) ? (int) $row["c{$index}_min_length"] : null,
            'maxLength' => isset($row["c{$index}_max_length"]) ? (int) $row["c{$index}_max_length"] : null,
        ], array_keys($columns));
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  'PK'|'UQ'|'FK'|null  $key
     * @param  array{filled: int, distinct: ?int, min: mixed, max: mixed, averageLength: ?float, minLength: ?int, maxLength: ?int}  $stats
     * @return array{name: string, type: string, key: ?string, kind: string, nullCount: int, nullRatio: float, distinctCount: ?int, min: mixed, max: mixed, averageLength: ?float, minLength: ?int, maxLength: ?int, topValues: list<array{value: mixed, count: int}>}
     */
    protected function profileColumn(array $column, string $kind, ?string $key, array $stats, string $sample, int $sampleSize): array
    {
        $name = $this->quoteIdentifier($column['name']);
        $nullCount = $sampleSize - $stats['filled'];

        $topValues = $kind !== 'other'
            ? $this->driver->executeReadOnly(
                "SELECT {$name} AS value, COUNT(*) AS occurrences FROM {$sample} WHERE {$name} IS NOT NULL GROUP BY {$name} ORDER BY occurrences DESC, {$name} LIMIT ".self::TOP_VALUES
            )
            : [];

        return [
            'name' => $column['name'],
            'type' => $column['type'],
            'key' => $key,
            'kind' => $kind,
            'nullCount' => $nullCount,
            'nullRatio' => $sampleSize > 0 ? round($nullCount / $sampleSize, 4) : 0.0,
            'distinctCount' => $stats['distinct'],
            'min' => SelectQuery::displayableValue($stats['min']),
            'max' => SelectQuery::displayableValue($stats['max']),
            'averageLength' => $stats['averageLength'],
            'minLength' => $stats['minLength'],
            'maxLength' => $stats['maxLength'],
            'topValues' => array_map(fn (object $row): array => [
                'value' => SelectQuery::displayableValue($row->value),
                'count' => (int) $row->occurrences,
            ], $topValues),
        ];
    }

    /**
     * The key each column forms on its own: the primary key first, then a unique index, then a foreign key.
     *
     * @param  list<array<string, mixed>>  $indexes
     * @param  list<array<string, mixed>>  $foreignKeys
     * @return array<string, 'PK'|'UQ'|'FK'>
     */
    protected function keys(array $indexes, array $foreignKeys): array
    {
        $keys = [];

        foreach ($foreignKeys as $foreignKey) {
            foreach ($foreignKey['columns'] as $column) {
                $keys[$column] = 'FK';
            }
        }

        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) && count($index['columns']) === 1) {
                $keys[$index['columns'][0]] = 'UQ';
            }
        }

        foreach ($indexes as $index) {
            if ($index['primary'] ?? false) {
                foreach ($index['columns'] as $column) {
                    $keys[$column] = 'PK';
                }
            }
        }

        return $keys;
    }

    /**
     * The kind of statistics a column supports, across MySQL, MariaDB, PostgreSQL and SQLite.
     *
     * @param  array<string, mixed>  $column
     */
    protected function kind(array $column): string
    {
        $typeName = strtolower((string) $column['type_name']);

        return match (true) {
            strtolower((string) $column['type']) === 'tinyint(1)', in_array($typeName, ['bool', 'boolean'], true) => 'boolean',
            in_array($typeName, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'int2', 'int4', 'int8', 'year', 'decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8', 'money'], true) => 'numeric',
            in_array($typeName, ['date', 'datetime', 'timestamp', 'timestamptz', 'time', 'timetz'], true) => 'temporal',
            in_array($typeName, ['char', 'varchar', 'character', 'character varying', 'bpchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'clob'], true) => 'text',
            in_array($typeName, ['json', 'jsonb', 'blob', 'tinyblob', 'mediumblob', 'longblob', 'bytea', 'binary', 'varbinary', 'geometry', 'point', 'xml'], true) => 'other',
            default => 'categorical',
        };
    }
}
