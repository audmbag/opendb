<?php

declare(strict_types=1);

namespace App\Database\Schema;

use AlbertoArena\Truss\Introspection\Data\Column;
use AlbertoArena\Truss\Introspection\Data\ForeignKey;
use AlbertoArena\Truss\Introspection\Data\Index;
use AlbertoArena\Truss\Introspection\Data\Table;
use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Introspection de truss sans le découpage « schéma.table » de Laravel.
 *
 * Schema::getColumns(), getIndexes() et getForeignKeys() lisent « 1.5e3 » comme la table « 5e3 » du schéma « 1 » :
 * la table revient vide sur MySQL et PostgreSQL, et SQLite échoue (« unknown database '1' »). Les requêtes sont
 * donc compilées par la grammaire, qui reçoit le schéma courant et le nom de la table séparément.
 */
class SnapshotBuilder extends TrussSnapshotBuilder
{
    /**
     * @return list<Table>
     */
    public function introspect(string $connection): array
    {
        $connection = DB::connection($connection);
        $builder = $connection->getSchemaBuilder();
        $schema = $builder->getCurrentSchemaName();

        return array_map(
            fn (array $table): Table => $this->table($connection, $schema, $table['name']),
            $builder->getTables($schema),
        );
    }

    /**
     * Describe a table of the current schema as Laravel's schema builder does, without splitting a dotted name.
     *
     * @return array{columns: list<array<string, mixed>>, indexes: list<array<string, mixed>>, foreign_keys: list<array<string, mixed>>}
     */
    public function describe(Connection $connection, string $name, ?string $schema = null): array
    {
        $grammar = $connection->getSchemaGrammar();
        $processor = $connection->getPostProcessor();
        $schema ??= $connection->getSchemaBuilder()->getCurrentSchemaName();

        return [
            'columns' => $processor->processColumns($connection->selectFromWriteConnection($grammar->compileColumns($schema, $name))),
            'indexes' => $processor->processIndexes($connection->selectFromWriteConnection($grammar->compileIndexes($schema, $name))),
            'foreign_keys' => $processor->processForeignKeys($connection->selectFromWriteConnection($grammar->compileForeignKeys($schema, $name))),
        ];
    }

    private function table(Connection $connection, ?string $schema, string $name): Table
    {
        ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys] = $this->describe($connection, $name, $schema);

        $primaryKey = [];
        $secondaryIndexes = [];
        foreach ($indexes as $index) {
            if ($index['primary'] ?? false) {
                $primaryKey = array_values($index['columns']);

                continue;
            }

            $secondaryIndexes[] = new Index(name: $index['name'], columns: array_values($index['columns']), unique: (bool) $index['unique']);
        }

        return new Table(
            name: $name,
            columns: array_map(fn (array $column): Column => new Column(
                name: $column['name'],
                type: $column['type'],
                nullable: (bool) $column['nullable'],
                default: $column['default'] !== null ? (string) $column['default'] : null,
            ), $columns),
            primaryKey: $primaryKey,
            indexes: $secondaryIndexes,
            foreignKeys: array_map(fn (array $foreignKey): ForeignKey => new ForeignKey(
                name: (string) ($foreignKey['name'] ?? ''),
                columns: array_values($foreignKey['columns']),
                referencesTable: $foreignKey['foreign_table'],
                referencesColumns: array_values($foreignKey['foreign_columns']),
                onUpdate: ($foreignKey['on_update'] ?? '') !== '' ? $foreignKey['on_update'] : null,
                onDelete: ($foreignKey['on_delete'] ?? '') !== '' ? $foreignKey['on_delete'] : null,
            ), $foreignKeys),
        );
    }
}
