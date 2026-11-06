<?php

namespace App\Database\Schema;

use Illuminate\Database\Connection;

/**
 * A CREATE TABLE statement rebuilt from the description of a table, for the engines that cannot print their own, like PostgreSQL.
 */
class TableDefinitionBuilder
{
    public function __construct(protected SnapshotBuilder $snapshot) {}

    public function build(Connection $connection, string $table): string
    {
        ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys] = $this->snapshot->describe($connection, $table);

        $quote = fn (string $name): string => Identifier::quote($connection, $name);
        $quoteList = fn (array $names): string => implode(', ', array_map($quote, $names));

        $primary = array_find($indexes, fn (array $index): bool => (bool) $index['primary']);

        $definitions = [
            ...array_map(fn (array $column): string => implode(' ', array_filter([
                $quote($column['name']),
                $column['type'],
                filled($column['generation']['expression'] ?? null) ? "GENERATED ALWAYS AS ({$column['generation']['expression']}) STORED" : null,
                $column['nullable'] ? null : 'NOT NULL',
                $column['default'] !== null && ! filled($column['generation'] ?? null) ? "DEFAULT {$column['default']}" : null,
            ])), $columns),
            ...($primary !== null ? ["PRIMARY KEY ({$quoteList($primary['columns'])})"] : []),
            ...array_map(fn (array $foreignKey): string => implode(' ', array_filter([
                filled($foreignKey['name'] ?? null) ? "CONSTRAINT {$quote($foreignKey['name'])}" : null,
                "FOREIGN KEY ({$quoteList($foreignKey['columns'])})",
                "REFERENCES {$quote($foreignKey['foreign_table'])} ({$quoteList($foreignKey['foreign_columns'])})",
                $this->action('ON UPDATE', $foreignKey['on_update'] ?? null),
                $this->action('ON DELETE', $foreignKey['on_delete'] ?? null),
            ])), $foreignKeys),
        ];

        $statements = ["CREATE TABLE {$quote($table)} (\n    ".implode(",\n    ", $definitions)."\n);"];

        foreach ($indexes as $index) {
            if (! $index['primary']) {
                $statements[] = ($index['unique'] ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
                    ."{$quote($index['name'])} ON {$quote($table)} ({$quoteList($index['columns'])});";
            }
        }

        return implode("\n\n", $statements);
    }

    protected function action(string $clause, ?string $action): ?string
    {
        return filled($action) && strtolower($action) !== 'no action' ? "{$clause} ".strtoupper($action) : null;
    }
}
