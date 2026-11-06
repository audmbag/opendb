<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\SnapshotBuilder;
use App\Services\TableBrowser\TableCatalog;

/**
 * The structure of an existing table in the shape the designer edits, each element keeping the name it has in the
 * database so that the changes of the user can be told apart from what stays.
 *
 * @phpstan-import-type ColumnDesign from ColumnTypeCatalog
 *
 * @phpstan-type IndexDesign array{original: ?string, name: ?string, columns: list<string>, unique: bool}
 * @phpstan-type ForeignKeyDesign array{original: ?string, name: ?string, columns: list<string>, referencesTable: string, referencesColumns: list<string>, onDelete: ?string, onUpdate: ?string}
 * @phpstan-type TableDesign array{name: string, columns: list<ColumnDesign>, primaryKey: list<string>, indexes: list<IndexDesign>, foreignKeys: list<ForeignKeyDesign>}
 */
class TableDesignReader
{
    public function __construct(
        protected SnapshotBuilder $snapshot,
        protected TableCatalog $catalog,
        protected ColumnTypeCatalog $types,
    ) {}

    /**
     * @return TableDesign
     */
    public function read(DriverContract $driver, string $table): array
    {
        $this->catalog->definition($driver, $table);
        $description = $this->snapshot->describe($driver->getConnection(), $table);
        $primary = array_find($description['indexes'], fn (array $index): bool => (bool) $index['primary']);

        return [
            'name' => $table,
            'columns' => array_map(fn (array $column): array => [
                'original' => $column['name'],
                'changed' => false,
                'name' => $column['name'],
                ...$this->types->fromDatabase($column),
                'dbType' => $column['type'],
                'nullable' => (bool) $column['nullable'],
                ...$this->types->defaultFromDatabase($column['default'] ?? null),
                'autoIncrement' => (bool) ($column['auto_increment'] ?? false),
            ], $description['columns']),
            'primaryKey' => array_values($primary['columns'] ?? []),
            'primaryKeyName' => $primary['name'] ?? null,
            'indexes' => array_values(array_map(fn (array $index): array => [
                'original' => $index['name'],
                'name' => $index['name'],
                'columns' => array_values($index['columns']),
                'unique' => (bool) $index['unique'],
            ], array_filter($description['indexes'], fn (array $index): bool => ! $index['primary']))),
            'foreignKeys' => array_map(fn (array $foreignKey): array => [
                'original' => $foreignKey['name'] ?? implode('_', $foreignKey['columns']),
                'name' => $foreignKey['name'] ?? null,
                'columns' => array_values($foreignKey['columns']),
                'referencesTable' => $foreignKey['foreign_table'],
                'referencesColumns' => array_values($foreignKey['foreign_columns']),
                'onDelete' => $this->action($foreignKey['on_delete'] ?? null),
                'onUpdate' => $this->action($foreignKey['on_update'] ?? null),
            ], $description['foreign_keys']),
        ];
    }

    protected function action(?string $action): ?string
    {
        return $action === null || strtolower($action) === 'no action' ? null : strtolower($action);
    }
}
