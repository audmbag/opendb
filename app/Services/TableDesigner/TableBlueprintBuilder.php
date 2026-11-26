<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Schema\Identifier;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;

/**
 * The statements that give a table the structure of a design, written by Laravel's schema builder for the engine.
 *
 * They are compiled in phases, each one only once the previous ones have run: SQLite rebuilds a table to change
 * it, from its structure as it is when the statements are compiled.
 *
 * @phpstan-import-type TableDesign from TableDesignReader
 * @phpstan-import-type ForeignKeyDesign from TableDesignReader
 */
class TableBlueprintBuilder
{
    public function __construct(protected ColumnTypeCatalog $types, protected ColumnPositioner $positioner) {}

    /**
     * @param  TableDesign  $design
     * @return list<Closure(): list<string>>
     */
    public function create(Connection $connection, array $design): array
    {
        return [fn (): array => $this->blueprint($connection, $design['name'], function (Blueprint $table) use ($design): void {
            $table->create();

            foreach ($design['columns'] as $column) {
                $this->types->define($table, $column);
            }

            if ($design['primaryKey'] !== [] && ! $this->primaryKeyIsAutoIncrement($design)) {
                $table->primary($design['primaryKey']);
            }

            $this->addIndexes($table, $design['indexes']);
            $this->addForeignKeys($table, $design['foreignKeys']);
        })->toSql()];
    }

    /**
     * @param  TableDesign&array{primaryKeyName: ?string}  $current  the table as the database describes it
     * @param  TableDesign  $design  the table as the user wants it
     * @return list<Closure(): list<string>>
     *
     * @throws TableDesignFailedException
     */
    public function alter(Connection $connection, array $current, array $design): array
    {
        $table = $current['name'];
        $sqlite = $connection->getDriverName() === 'sqlite';
        $kept = array_filter(array_column($design['columns'], 'original'));
        $dropped = array_values(array_diff(array_column($current['columns'], 'name'), $kept));
        $renames = array_column(array_filter($design['columns'], fn (array $column): bool => $column['original'] !== null && $column['original'] !== $column['name']), 'name', 'original');
        $touchesDropped = fn (array $element): bool => array_intersect($element['columns'], $dropped) !== [];

        $keptIndexes = array_filter(array_column($design['indexes'], 'original'));
        $droppedIndexes = array_filter($current['indexes'], fn (array $index): bool => ! in_array($index['original'], $keptIndexes, true) || $touchesDropped($index));
        $keptForeignKeys = array_filter(array_column($design['foreignKeys'], 'original'));
        $droppedForeignKeys = array_filter($current['foreignKeys'], fn (array $foreignKey): bool => ! in_array($foreignKey['original'], $keptForeignKeys, true) || $touchesDropped($foreignKey));

        $currentPrimaryKey = array_map(fn (string $column): string => $renames[$column] ?? $column, $current['primaryKey']);
        $primaryKeyChanged = $currentPrimaryKey !== $design['primaryKey'];

        $phases = [];

        $phases[] = function () use ($connection, $table, $sqlite, $current, $dropped, $renames, $droppedIndexes, $droppedForeignKeys, $primaryKeyChanged): array {
            $dropsPrimaryKey = $primaryKeyChanged && $current['primaryKey'] !== [];
            $postgresPrimaryKey = $dropsPrimaryKey && $connection->getDriverName() === 'pgsql' && $current['primaryKeyName'] !== null;

            $statements = $this->blueprint($connection, $table, function (Blueprint $blueprint) use ($sqlite, $dropped, $renames, $droppedIndexes, $droppedForeignKeys, $dropsPrimaryKey, $postgresPrimaryKey): void {
                foreach ($droppedForeignKeys as $foreignKey) {
                    $blueprint->dropForeign($sqlite || $foreignKey['name'] === null ? $foreignKey['columns'] : $foreignKey['name']);
                }

                foreach ($droppedIndexes as $index) {
                    $index['unique'] ? $blueprint->dropUnique($index['name']) : $blueprint->dropIndex($index['name']);
                }

                if ($dropsPrimaryKey && ! $postgresPrimaryKey) {
                    $blueprint->dropPrimary();
                }

                if ($dropped !== []) {
                    $blueprint->dropColumn($dropped);
                }

                foreach ($renames as $from => $to) {
                    $blueprint->renameColumn($from, $to);
                }
            })->toSql();

            // PostgreSQL's grammar names the key after the table: the database may have given it another name.
            return $postgresPrimaryKey
                ? ['alter table '.Identifier::quote($connection, $table).' drop constraint '.Identifier::quote($connection, $current['primaryKeyName']), ...$statements]
                : $statements;
        };

        $phases[] = fn (): array => $this->blueprint($connection, $table, function (Blueprint $blueprint) use ($design, $primaryKeyChanged): void {
            foreach ($design['columns'] as $column) {
                if ($column['original'] === null) {
                    $this->types->define($blueprint, $column);
                } elseif ($column['changed']) {
                    $this->types->define($blueprint, $column)->change();
                }
            }

            if ($primaryKeyChanged && $design['primaryKey'] !== [] && ! $this->primaryKeyIsAutoIncrement($design)) {
                $blueprint->primary($design['primaryKey']);
            }

            $this->addIndexes($blueprint, array_filter($design['indexes'], fn (array $index): bool => $index['original'] === null));
            $this->addForeignKeys($blueprint, array_filter($design['foreignKeys'], fn (array $foreignKey): bool => $foreignKey['original'] === null));
        })->toSql();

        // The columns kept stay where they are and the new ones come last, unless the design puts them elsewhere.
        $natural = [
            ...array_map(fn (string $column): string => $renames[$column] ?? $column, array_values(array_diff(array_column($current['columns'], 'name'), $dropped))),
            ...array_column(array_filter($design['columns'], fn (array $column): bool => $column['original'] === null), 'name'),
        ];

        if ($natural !== array_column($design['columns'], 'name')) {
            $phases[] = $this->reorder($connection, $table, $design, $natural);
        }

        if ($design['name'] !== $table) {
            $phases[] = fn (): array => $this->blueprint($connection, $table, function (Blueprint $blueprint) use ($design): void {
                $blueprint->rename($design['name']);
            })->toSql();
        }

        return $phases;
    }

    /**
     * The statements putting the columns in the order of the design, once they are all added, changed and renamed.
     * MySQL moves the fewest columns it can, each from the definition it has once changed: the one MySQL gives of
     * the columns left as they were, read before anything changes, and the design for the others.
     *
     * @param  TableDesign  $design
     * @param  list<string>  $natural  the order of the columns once changed, before they are put in order
     * @return Closure(): list<string>
     *
     * @throws TableDesignFailedException
     */
    protected function reorder(Connection $connection, string $table, array $design, array $natural): Closure
    {
        $order = array_column($design['columns'], 'name');

        if (! $this->positioner->supports($connection)) {
            throw new TableDesignFailedException('', __('tables.errors.column_move_unsupported'));
        }

        if ($connection->getDriverName() === 'sqlite') {
            return fn (): array => $this->positioner->rebuildSqliteTable($connection, $table, $order);
        }

        $definitions = $this->positioner->mysqlDefinitions($connection, $table);
        $columns = array_column($design['columns'], null, 'name');

        return fn (): array => array_merge(...array_map(function (array $move) use ($connection, $table, $definitions, $columns): array {
            $column = $columns[$move['column']];

            if ($column['original'] !== null && ! $column['changed'] && isset($definitions[$column['original']])) {
                return [$this->positioner->mysqlMove($connection, $table, $column['name'], $definitions[$column['original']], $move['after'])];
            }

            return $this->blueprint($connection, $table, function (Blueprint $blueprint) use ($column, $move): void {
                $definition = $this->types->define($blueprint, $column)->change();
                $move['after'] === null ? $definition->first() : $definition->after($move['after']);
            })->toSql();
        }, $this->positioner->moves($natural, $order)));
    }

    /**
     * A blueprint of the table. The schema grammar it compiles with is only set once a schema builder is made.
     *
     * @param  Closure(Blueprint): void  $callback
     */
    protected function blueprint(Connection $connection, string $table, Closure $callback): Blueprint
    {
        $connection->getSchemaBuilder();

        return new Blueprint($connection, $table, $callback);
    }

    /**
     * @param  TableDesign  $design
     */
    protected function primaryKeyIsAutoIncrement(array $design): bool
    {
        $autoIncrement = array_find($design['columns'], fn (array $column): bool => $column['autoIncrement']);

        return $autoIncrement !== null && $design['primaryKey'] === [$autoIncrement['name']];
    }

    /**
     * @param  iterable<array{name: ?string, columns: list<string>, unique: bool}>  $indexes
     */
    protected function addIndexes(Blueprint $table, iterable $indexes): void
    {
        foreach ($indexes as $index) {
            $name = filled($index['name'] ?? null) ? $index['name'] : null;
            $index['unique'] ? $table->unique($index['columns'], $name) : $table->index($index['columns'], $name);
        }
    }

    /**
     * @param  iterable<ForeignKeyDesign>  $foreignKeys
     */
    protected function addForeignKeys(Blueprint $table, iterable $foreignKeys): void
    {
        foreach ($foreignKeys as $foreignKey) {
            /** @var ForeignKeyDefinition $definition */
            $definition = $table->foreign($foreignKey['columns'], filled($foreignKey['name'] ?? null) ? $foreignKey['name'] : null)
                ->references($foreignKey['referencesColumns'])
                ->on($foreignKey['referencesTable']);

            if ($foreignKey['onDelete'] !== null) {
                $definition->onDelete($foreignKey['onDelete']);
            }

            if ($foreignKey['onUpdate'] !== null) {
                $definition->onUpdate($foreignKey['onUpdate']);
            }
        }
    }
}
