<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\ColumnType;
use App\Database\Schema\SnapshotBuilder;

/**
 * Every column of a table with what the grid needs to display and edit it, read from the database itself.
 */
class TableColumns
{
    public function __construct(
        protected SnapshotBuilder $snapshot,
        protected TableCatalog $catalog,
    ) {}

    /**
     * @return array{primaryKey: list<string>, columns: list<array{name: string, kind: string, db_type: string, nullable: bool, default: ?string, primary: bool, auto_increment: bool, generated: bool, editable: bool, max_length: ?int, options: ?list<string>, unique: bool, references: ?array{table: string, column: string}}>}
     */
    public function describe(DriverContract $driver, string $table): array
    {
        $this->catalog->definition($driver, $table);

        ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys] = $this->snapshot->describe($driver->getConnection(), $table);

        $primaryKey = array_values(array_find($indexes, fn (array $index): bool => (bool) $index['primary'])['columns'] ?? []);

        $uniqueColumns = collect($indexes)
            ->filter(fn (array $index): bool => $index['unique'] && ! $index['primary'] && count($index['columns']) === 1)
            ->map(fn (array $index): string => $index['columns'][0])
            ->all();

        $references = collect($foreignKeys)
            ->filter(fn (array $foreignKey): bool => count($foreignKey['columns']) === 1)
            ->mapWithKeys(fn (array $foreignKey): array => [$foreignKey['columns'][0] => [
                'table' => $foreignKey['foreign_table'],
                'column' => $foreignKey['foreign_columns'][0],
            ]])
            ->all();

        return [
            'primaryKey' => $primaryKey,
            'columns' => array_map(function (array $column) use ($primaryKey, $uniqueColumns, $references): array {
                $binary = ColumnType::isBinary($column);
                $kind = $binary ? 'binary' : ColumnType::of($column);
                $generated = filled($column['generation'] ?? null);

                return [
                    'name' => $column['name'],
                    'kind' => $kind,
                    'db_type' => $column['type'],
                    'nullable' => (bool) $column['nullable'],
                    'default' => ColumnType::defaultValue($column['default'] ?? null),
                    'primary' => in_array($column['name'], $primaryKey, true),
                    'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                    'generated' => $generated,
                    'editable' => ! $binary && ! $generated,
                    'max_length' => in_array($kind, ['string', 'uuid'], true) ? ColumnType::maxLength($column['type']) : null,
                    'options' => $kind === 'enum' ? ColumnType::enumOptions($column['type']) : null,
                    'unique' => in_array($column['name'], $uniqueColumns, true),
                    'references' => $references[$column['name']] ?? null,
                ];
            }, $columns),
        ];
    }
}
