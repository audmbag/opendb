<?php

declare(strict_types=1);

namespace App\Database\Schema;

use AlbertoArena\Truss\Introspection\Data\Column;
use AlbertoArena\Truss\Introspection\Data\ForeignKey;
use AlbertoArena\Truss\Introspection\Data\Index;
use AlbertoArena\Truss\Introspection\Data\Table;
use AlbertoArena\Truss\Introspection\SchemaSerializer;
use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Introspection de truss sans le découpage « schéma.table » de Laravel.
 *
 * Schema::getColumns(), getIndexes() et getForeignKeys() lisent « 1.5e3 » comme la table « 5e3 » du schéma « 1 » :
 * la table revient vide sur MySQL et PostgreSQL, et SQLite échoue (« unknown database '1' »). Les requêtes sont
 * donc compilées par la grammaire, qui reçoit le schéma courant et le nom de la table séparément.
 *
 * Every table is read at once where the driver allows it, and the description of each one is kept with the schema,
 * so that describing a table afterwards asks nothing of the server.
 *
 * A database that cannot be reached fails: Truss would replay the migrations of opendb instead, and describe the
 * tables of opendb itself as those of the project, to the pages and to the agent.
 *
 * @phpstan-import-type TableDescription from SchemaIntrospector
 */
class SnapshotBuilder extends TrussSnapshotBuilder
{
    /**
     * @return array{connection: string, fallback: bool, skipped_migrations: list<string>, tables: list<array<string, mixed>>}
     *
     * @throws Throwable when the database cannot be reached or read
     */
    public function build(?string $connection = null): array
    {
        $connection ??= (string) config('database.default');

        return [
            'connection' => $connection,
            'fallback' => false,
            'skipped_migrations' => [],
            'tables' => new SchemaSerializer()->tables($this->introspect($connection)),
        ];
    }

    /**
     * @return list<Table>
     */
    public function introspect(string $connection): array
    {
        $connection = DB::connection($connection);
        $builder = $connection->getSchemaBuilder();
        $schema = $builder->getCurrentSchemaName();
        $tables = $builder->getTables($schema);
        $names = array_map(fn (array $table): string => (string) $table['name'], $tables);

        $descriptions = app(SchemaIntrospector::class)->describeAll($connection, (string) $schema, $names)
            ?? array_combine($names, array_map(fn (string $table): array => $this->query($connection, $table, $schema), $names));

        app(SchemaCache::class)->store($connection, $descriptions, app(SchemaIntrospector::class)->comments($tables, $descriptions));

        return array_map(fn (string $table): Table => $this->table($table, $descriptions[$table]), $names);
    }

    /**
     * Describe a table of the current schema as Laravel's schema builder does, without splitting a dotted name.
     * The description is kept with the schema, and forgotten with it.
     *
     * @return TableDescription
     */
    public function describe(Connection $connection, string $name, ?string $schema = null): array
    {
        if ($schema !== null) {
            return $this->query($connection, $name, $schema);
        }

        return app(SchemaCache::class)->description($connection, $name, fn (): array => $this->query($connection, $name));
    }

    /**
     * @return TableDescription
     */
    private function query(Connection $connection, string $name, ?string $schema = null): array
    {
        // The schema grammar is only set once a schema builder is made: with the tables already cached, nothing else made one.
        $builder = $connection->getSchemaBuilder();
        $grammar = $connection->getSchemaGrammar();
        $processor = $connection->getPostProcessor();
        $schema ??= $builder->getCurrentSchemaName();

        return [
            'columns' => $processor->processColumns($connection->selectFromWriteConnection($grammar->compileColumns($schema, $name))),
            'indexes' => $processor->processIndexes($connection->selectFromWriteConnection($grammar->compileIndexes($schema, $name))),
            'foreign_keys' => $processor->processForeignKeys($connection->selectFromWriteConnection($grammar->compileForeignKeys($schema, $name))),
        ];
    }

    /**
     * @param  TableDescription  $description
     */
    private function table(string $name, array $description): Table
    {
        ['columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => $foreignKeys] = $description;

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
