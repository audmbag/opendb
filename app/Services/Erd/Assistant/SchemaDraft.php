<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

/**
 * The tables of a plan drawn in the diagram before it runs, in the shape of the schema the diagram already draws:
 * the tables created or changed as they will be, each column marked as added, changed or dropped, and the tables
 * dropped.
 *
 * @phpstan-import-type TableDesign from \App\Services\TableDesigner\TableDesignReader
 *
 * @phpstan-type DraftColumn array{name: string, type: string, nullable: bool, default: ?string, draft: 'added'|'changed'|'dropped'|null}
 * @phpstan-type DraftTable array{name: string, replaces: ?string, draft: 'new'|'changed', columns: list<DraftColumn>, primary_key: list<string>, indexes: list<array{name: string, columns: list<string>, unique: bool}>, foreign_keys: list<array{name: string, columns: list<string>, references_table: string, references_columns: list<string>, on_update: string, on_delete: string, draft: bool}>}
 */
class SchemaDraft
{
    /**
     * @return array{tables: list<DraftTable>, dropped: list<string>}
     */
    public function from(SchemaChangePlan $plan): array
    {
        $tables = [];
        $dropped = [];

        foreach ($plan->changes as $change) {
            if (! $change->isValid()) {
                continue;
            }

            match ($change->kind) {
                SchemaChange::CREATE => $tables[] = $this->table($change->design, null, 'new', [], [...$change->design['foreignKeys'], ...$change->deferredForeignKeys]),
                SchemaChange::ALTER => $tables[] = $this->table($change->design, $change->from, 'changed', $change->current ?? [], $change->design['foreignKeys']),
                SchemaChange::DROP => $dropped[] = (string) $change->from,
                default => null,
            };
        }

        return ['tables' => $tables, 'dropped' => $dropped];
    }

    /**
     * @param  TableDesign  $design
     * @param  array<string, mixed>  $current  the table as the database describes it, empty for a new one
     * @param  list<array<string, mixed>>  $foreignKeys
     * @return DraftTable
     */
    protected function table(array $design, ?string $from, string $status, array $current, array $foreignKeys): array
    {
        $currentColumns = collect($current['columns'] ?? [])->keyBy('name');
        $columns = array_map(function (array $column) use ($currentColumns, $current): array {
            $before = $column['original'] === null ? null : $currentColumns->get($column['original']);

            return [
                'name' => $column['name'],
                'type' => $before !== null && ! $column['changed'] ? (string) ($before['dbType'] ?? $this->typeLabel($column)) : $this->typeLabel($column),
                'nullable' => $column['nullable'],
                'default' => $column['defaultKind'] === 'none' ? null : ($column['defaultKind'] === 'current' ? 'CURRENT_TIMESTAMP' : $column['default']),
                'draft' => match (true) {
                    $current === [] => null,
                    $column['original'] === null => 'added',
                    $column['changed'] || $column['original'] !== $column['name'] => 'changed',
                    default => null,
                },
            ];
        }, $design['columns']);

        $kept = array_filter(array_column($design['columns'], 'original'));

        foreach ($current['columns'] ?? [] as $column) {
            if (! in_array($column['name'], $kept, true)) {
                $columns[] = ['name' => $column['name'], 'type' => (string) ($column['dbType'] ?? $column['type']), 'nullable' => (bool) $column['nullable'], 'default' => null, 'draft' => 'dropped'];
            }
        }

        return [
            'name' => $design['name'],
            'replaces' => $from !== null && $from !== $design['name'] ? $from : null,
            'draft' => $status,
            'columns' => $columns,
            'primary_key' => $design['primaryKey'],
            'indexes' => array_map(fn (array $index): array => [
                'name' => $index['name'] ?? implode('_', [$design['name'], ...$index['columns'], $index['unique'] ? 'unique' : 'index']),
                'columns' => $index['columns'],
                'unique' => $index['unique'],
            ], $design['indexes']),
            'foreign_keys' => array_map(fn (array $foreignKey): array => [
                'name' => (string) ($foreignKey['name'] ?? implode('_', [$design['name'], ...$foreignKey['columns'], 'foreign'])),
                'columns' => $foreignKey['columns'],
                'references_table' => $foreignKey['referencesTable'],
                'references_columns' => $foreignKey['referencesColumns'],
                'on_update' => (string) ($foreignKey['onUpdate'] ?? 'no action'),
                'on_delete' => (string) ($foreignKey['onDelete'] ?? 'no action'),
                'draft' => $foreignKey['original'] === null,
            ], array_values($foreignKeys)),
        ];
    }

    /**
     * The type of a column of the designer written as the database would show it.
     *
     * @param  array<string, mixed>  $column
     */
    protected function typeLabel(array $column): string
    {
        return match ($column['type']) {
            'string' => 'varchar('.($column['length'] ?? 255).')',
            'char' => 'char('.($column['length'] ?? 255).')',
            'decimal' => 'decimal('.($column['precision'] ?? 8).','.($column['scale'] ?? 2).')',
            'enum' => 'enum('.implode(', ', array_map(fn (string $option): string => "'{$option}'", $column['options'])).')',
            'tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger' => strtolower(str_replace('Integer', 'int', $column['type'])).($column['unsigned'] ? ' unsigned' : ''),
            default => strtolower((string) $column['type']),
        };
    }
}
