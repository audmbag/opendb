<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

use App\Database\AdminStatementGuard;
use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\HiddenTableGuard;
use App\Database\StackedStatementGuard;
use App\Enums\PrivilegeEnum;
use App\Services\TableDesigner\ColumnTypeCatalog;
use App\Services\TableDesigner\TableDesignReader;
use App\Services\TableDesigner\TableDesignValidator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The changes of structure the assistant asks for, written in a short form, read against the database: each one
 * becomes the complete design the designer of tables runs, checked by the same rules as its form.
 *
 * @phpstan-import-type TableDesign from TableDesignReader
 * @phpstan-import-type ForeignKeyDesign from TableDesignReader
 * @phpstan-import-type ColumnDesign from ColumnTypeCatalog
 */
class SchemaChangePlanner
{
    /**
     * The statements the assistant may run as they are: they change the structure, never the rows.
     */
    private const string RAW_STATEMENT = '/^\s*(create|alter|drop|comment|rename)\b/i';

    /**
     * The attributes of a column the assistant may give.
     */
    private const array COLUMN_ATTRIBUTES = ['type', 'length', 'precision', 'scale', 'unsigned', 'nullable', 'default', 'defaultKind', 'autoIncrement', 'options'];

    public function __construct(
        protected TableDesignReader $reader,
        protected TableDesignValidator $validator,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $changes  the changes as the assistant writes them
     */
    public function plan(DriverContract $driver, array $changes): SchemaChangePlan
    {
        $existing = $driver->listTables(false);
        $hidden = $driver->hiddenTables();
        $created = array_values(array_filter(array_map(
            fn (mixed $change): ?string => is_array($change) && ($change['action'] ?? null) === SchemaChange::CREATE && is_string($change['table'] ?? null) ? $change['table'] : null,
            $changes,
        )));
        $touched = [];
        $planned = [];

        foreach (array_values($changes) as $change) {
            $change = is_array($change) ? $change : [];
            $kind = (string) ($change['action'] ?? '');
            $table = is_string($change['table'] ?? null) ? $change['table'] : null;

            try {
                if (! in_array($kind, SchemaChange::KINDS, true)) {
                    throw new RuntimeException(__('erd.assistant.errors.unknown_action', ['action' => $kind]));
                }

                if ($kind !== SchemaChange::RAW) {
                    if ($table === null || $table === '') {
                        throw new RuntimeException(__('erd.assistant.errors.table_required'));
                    }

                    if (in_array($table, $hidden, true)) {
                        throw new RuntimeException(__('erd.assistant.errors.hidden', ['table' => $table]));
                    }

                    $driver->assertPrivilege(match ($kind) {
                        SchemaChange::CREATE => PrivilegeEnum::Create,
                        SchemaChange::ALTER => PrivilegeEnum::Alter,
                        default => PrivilegeEnum::Drop,
                    }, $kind === SchemaChange::CREATE ? null : $table);

                    if (in_array($table, $touched, true)) {
                        throw new RuntimeException(__('erd.assistant.errors.twice', ['table' => $table]));
                    }

                    $touched[] = $table;
                }

                $planned[] = match ($kind) {
                    SchemaChange::CREATE => $this->create($change, (string) $table, $existing, $created, $hidden),
                    SchemaChange::ALTER => $this->alter($driver, $change, (string) $table, $existing, $created, $hidden),
                    SchemaChange::DROP => $this->drop((string) $table, $existing),
                    SchemaChange::RAW => $this->raw($driver, $change, $hidden),
                };
            } catch (ValidationException $exception) {
                $planned[] = SchemaChange::failed($kind, $table, $this->messages($exception));
            } catch (RuntimeException $exception) {
                $planned[] = SchemaChange::failed($kind, $table, [$exception->getMessage()]);
            }
        }

        return new SchemaChangePlan($this->ordered($this->withForeignKeyTypes($driver, $planned)));
    }

    /**
     * The changes whose new foreign keys reference a missing column, or on MySQL and MariaDB a column of another
     * type, refused: these engines refuse a key between an INT UNSIGNED and a BIGINT, and their preview only
     * compiles the statements without running them.
     *
     * @param  list<SchemaChange>  $changes
     * @return list<SchemaChange>
     */
    protected function withForeignKeyTypes(DriverContract $driver, array $changes): array
    {
        $designs = [];

        foreach ($changes as $change) {
            if ($change->design !== null) {
                $designs[$change->table] = $change->design;
            }
        }

        $checksTypes = in_array($driver->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        $column = function (string $table, string $name) use ($driver, &$designs): ?array {
            if (! array_key_exists($table, $designs)) {
                try {
                    $designs[$table] = $this->reader->read($driver, $table);
                } catch (TableNotFoundException) {
                    $designs[$table] = null;
                }
            }

            return array_find($designs[$table]['columns'] ?? [], fn (array $column): bool => $column['name'] === $name);
        };

        return array_map(function (SchemaChange $change) use ($column, $checksTypes): SchemaChange {
            if (! $change->isValid() || $change->design === null) {
                return $change;
            }

            $errors = [];

            foreach ($change->design['foreignKeys'] as $foreignKey) {
                if ($foreignKey['original'] !== null) {
                    continue;
                }

                foreach ($foreignKey['columns'] as $index => $name) {
                    $local = $column((string) $change->table, $name);
                    $referencedName = $foreignKey['referencesColumns'][$index] ?? null;
                    $referenced = $referencedName === null ? null : $column($foreignKey['referencesTable'], $referencedName);

                    if ($referenced === null) {
                        $errors[] = __('erd.assistant.errors.referenced_column_missing', ['table' => $foreignKey['referencesTable'], 'column' => (string) $referencedName]);

                        continue;
                    }

                    if ($checksTypes && $local !== null && ! $this->sameKeyType($local, $referenced)) {
                        $errors[] = __('erd.assistant.errors.key_type', [
                            'column' => $name,
                            'type' => $this->keyType($local),
                            'reference' => "{$foreignKey['referencesTable']}.{$referencedName}",
                            'referenced_type' => $this->keyType($referenced),
                        ]);
                    }
                }
            }

            return $errors === [] ? $change : SchemaChange::failed($change->kind, $change->table, $errors);
        }, $changes);
    }

    /**
     * Whether a column can reference another one on MySQL and MariaDB: two integers of the same size and sign.
     * Other types are left to the database.
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $referenced
     */
    protected function sameKeyType(array $column, array $referenced): bool
    {
        $isInteger = fn (array $column): bool => in_array($column['type'], ColumnTypeCatalog::INTEGER_TYPES, true);

        if (! $isInteger($column) && ! $isInteger($referenced)) {
            return true;
        }

        return $column['type'] === $referenced['type'] && (bool) $column['unsigned'] === (bool) $referenced['unsigned'];
    }

    /**
     * @param  array<string, mixed>  $column
     */
    protected function keyType(array $column): string
    {
        return $column['type'].(($column['unsigned'] ?? false) ? ' unsigned' : '');
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  list<string>  $existing
     * @param  list<string>  $created
     * @param  list<string>  $hidden
     */
    protected function create(array $change, string $table, array $existing, array $created, array $hidden): SchemaChange
    {
        if (in_array($table, $existing, true)) {
            throw new RuntimeException(__('erd.assistant.errors.exists', ['table' => $table]));
        }

        $columns = array_map(fn (array $column): array => $this->column($column), $this->listOf($change['columns'] ?? []));
        $autoIncrement = array_find($columns, fn (array $column): bool => $column['autoIncrement']);
        $primaryKey = $this->strings($change['primaryKey'] ?? ($autoIncrement === null ? [] : [$autoIncrement['name']]));

        $design = $this->validator->validate([
            'name' => $table,
            'columns' => $columns,
            'primaryKey' => $primaryKey,
            'indexes' => array_map(fn (array $index): array => $this->index($index), $this->listOf($change['indexes'] ?? [])),
            'foreignKeys' => array_map(fn (array $foreignKey): array => $this->foreignKey($foreignKey), $this->listOf($change['foreignKeys'] ?? [])),
        ], null);

        $this->assertReferences($design['foreignKeys'], $existing, $created, $hidden);

        return new SchemaChange(SchemaChange::CREATE, $table, design: $design);
    }

    /**
     * The table as the database describes it, with the columns, indexes and keys the assistant adds, changes or
     * drops. The indexes and keys of a dropped column go with it, those of a renamed column follow it.
     *
     * @param  array<string, mixed>  $change
     * @param  list<string>  $existing
     * @param  list<string>  $created
     * @param  list<string>  $hidden
     */
    protected function alter(DriverContract $driver, array $change, string $table, array $existing, array $created, array $hidden): SchemaChange
    {
        if (! in_array($table, $existing, true)) {
            throw new RuntimeException(__('erd.assistant.errors.missing', ['table' => $table]));
        }

        try {
            $current = $this->reader->read($driver, $table);
        } catch (TableNotFoundException) {
            throw new RuntimeException(__('erd.assistant.errors.missing', ['table' => $table]));
        }

        $dropColumns = $this->strings($change['dropColumns'] ?? []);
        $renames = [];
        $destructive = $dropColumns !== [];
        $columns = [];

        foreach ($current['columns'] as $column) {
            if (in_array($column['name'], $dropColumns, true)) {
                continue;
            }

            $columns[] = $column;
        }

        foreach ($dropColumns as $name) {
            if (! in_array($name, array_column($current['columns'], 'name'), true)) {
                throw new RuntimeException(__('erd.assistant.errors.column_missing', ['table' => $table, 'column' => $name]));
            }
        }

        foreach ($this->listOf($change['changeColumns'] ?? []) as $changed) {
            $name = (string) ($changed['name'] ?? '');
            $position = array_find_key($columns, fn (array $column): bool => $column['name'] === $name);

            if ($position === null) {
                throw new RuntimeException(__('erd.assistant.errors.column_missing', ['table' => $table, 'column' => $name]));
            }

            $attributes = array_intersect_key($changed, array_flip(self::COLUMN_ATTRIBUTES));

            if ($attributes !== []) {
                $base = $columns[$position];

                // A new default value without a kind is a value, whatever the default the column had.
                if (array_key_exists('default', $attributes) && ! array_key_exists('defaultKind', $attributes)) {
                    unset($base['defaultKind']);
                }

                $columns[$position] = [...$this->column([...$base, ...$attributes]), 'original' => $name, 'changed' => true];
            }

            if (is_string($changed['newName'] ?? null) && $changed['newName'] !== $name) {
                $columns[$position]['name'] = $changed['newName'];
                $renames[$name] = $changed['newName'];
            }

            $destructive = true;
        }

        foreach ($this->listOf($change['addColumns'] ?? []) as $column) {
            $columns[] = $this->column($column);
        }

        $follow = fn (array $names): array => array_map(fn (string $column): string => $renames[$column] ?? $column, $names);
        $keeps = fn (array $element): bool => array_intersect($element['columns'], $dropColumns) === [];

        $dropIndexes = $this->strings($change['dropIndexes'] ?? []);
        $indexes = [];

        foreach ($current['indexes'] as $index) {
            if (in_array($index['name'], $dropIndexes, true) || ! $keeps($index)) {
                continue;
            }

            $indexes[] = [...$index, 'columns' => $follow($index['columns'])];
        }

        $dropForeignKeys = $this->strings($change['dropForeignKeys'] ?? []);
        $foreignKeys = [];

        foreach ($current['foreignKeys'] as $foreignKey) {
            $isDropped = in_array($foreignKey['name'], $dropForeignKeys, true)
                || in_array($foreignKey['original'], $dropForeignKeys, true)
                || in_array(implode(',', $foreignKey['columns']), $dropForeignKeys, true);

            if ($isDropped || ! $keeps($foreignKey)) {
                continue;
            }

            $foreignKeys[] = [...$foreignKey, 'columns' => $follow($foreignKey['columns'])];
        }

        $added = array_map(fn (array $foreignKey): array => $this->foreignKey($foreignKey), $this->listOf($change['addForeignKeys'] ?? []));
        $primaryKey = array_key_exists('primaryKey', $change) && is_array($change['primaryKey'])
            ? $this->strings($change['primaryKey'])
            : array_values(array_diff($follow($current['primaryKey']), $dropColumns));
        $rename = is_string($change['rename'] ?? null) && $change['rename'] !== '' && $change['rename'] !== $table ? $change['rename'] : null;

        if ($rename !== null && (in_array($rename, $existing, true) || in_array($rename, $created, true))) {
            throw new RuntimeException(__('erd.assistant.errors.exists', ['table' => $rename]));
        }

        $design = $this->validator->validate([
            'name' => $rename ?? $table,
            'columns' => $columns,
            'primaryKey' => $primaryKey,
            'indexes' => [...$indexes, ...array_map(fn (array $index): array => $this->index($index), $this->listOf($change['addIndexes'] ?? []))],
            'foreignKeys' => [...$foreignKeys, ...$added],
        ], $table);

        $this->assertReferences($added, $existing, $created, $hidden);

        $destructive = $destructive
            || $rename !== null
            || count($indexes) < count($current['indexes'])
            || count($foreignKeys) < count($current['foreignKeys'])
            || $design['primaryKey'] !== $follow($current['primaryKey']);

        return new SchemaChange(SchemaChange::ALTER, $design['name'], $table, $design, $current, destructive: $destructive);
    }

    /**
     * @param  list<string>  $existing
     */
    protected function drop(string $table, array $existing): SchemaChange
    {
        if (! in_array($table, $existing, true)) {
            throw new RuntimeException(__('erd.assistant.errors.missing', ['table' => $table]));
        }

        return new SchemaChange(SchemaChange::DROP, $table, $table, destructive: true);
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  list<string>  $hidden
     */
    protected function raw(DriverContract $driver, array $change, array $hidden): SchemaChange
    {
        $statement = trim(rtrim(trim((string) ($change['statement'] ?? '')), ';'));

        if ($statement === '' || ! preg_match(self::RAW_STATEMENT, $statement)) {
            throw new RuntimeException(__('erd.assistant.errors.raw_only_ddl'));
        }

        AdminStatementGuard::assertNotAdministrative($statement);
        StackedStatementGuard::assertSingleStatement($statement);

        if (HiddenTableGuard::rejects($statement, $hidden)) {
            throw new RuntimeException(__('erd.assistant.errors.raw_hidden'));
        }

        $driver->assertQueryAllowed($statement);

        return new SchemaChange(SchemaChange::RAW, null, statement: $statement, destructive: true);
    }

    /**
     * A column of the designer from the few attributes the assistant gives: a default value without a kind is
     * a value, and every other attribute takes the value of the form of the designer.
     *
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    protected function column(array $column): array
    {
        $default = $column['default'] ?? null;
        $default = match (true) {
            is_bool($default) => $default ? '1' : '0',
            is_scalar($default) => (string) $default,
            default => null,
        };

        return [
            'original' => $column['original'] ?? null,
            'changed' => (bool) ($column['changed'] ?? false),
            'name' => $column['name'] ?? null,
            'type' => $column['type'] ?? null,
            'length' => $column['length'] ?? null,
            'precision' => $column['precision'] ?? null,
            'scale' => $column['scale'] ?? null,
            'unsigned' => (bool) ($column['unsigned'] ?? false),
            'nullable' => (bool) ($column['nullable'] ?? false),
            'defaultKind' => $column['defaultKind'] ?? ($default === null ? 'none' : 'value'),
            'default' => $default,
            'autoIncrement' => (bool) ($column['autoIncrement'] ?? false),
            'options' => $this->strings($column['options'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $index
     * @return array<string, mixed>
     */
    protected function index(array $index): array
    {
        return [
            'original' => null,
            'name' => is_string($index['name'] ?? null) && $index['name'] !== '' ? $index['name'] : null,
            'columns' => $this->strings($index['columns'] ?? []),
            'unique' => (bool) ($index['unique'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $foreignKey
     * @return array<string, mixed>
     */
    protected function foreignKey(array $foreignKey): array
    {
        $action = fn (mixed $value): ?string => is_string($value) && $value !== '' && strtolower($value) !== 'no action' ? strtolower($value) : null;

        return [
            'original' => null,
            'name' => is_string($foreignKey['name'] ?? null) && $foreignKey['name'] !== '' ? $foreignKey['name'] : null,
            'columns' => $this->strings($foreignKey['columns'] ?? []),
            'referencesTable' => $foreignKey['referencesTable'] ?? null,
            'referencesColumns' => $this->strings($foreignKey['referencesColumns'] ?? []),
            'onDelete' => $action($foreignKey['onDelete'] ?? null),
            'onUpdate' => $action($foreignKey['onUpdate'] ?? null),
        ];
    }

    /**
     * @param  list<ForeignKeyDesign>|list<array<string, mixed>>  $foreignKeys
     * @param  list<string>  $existing
     * @param  list<string>  $created
     * @param  list<string>  $hidden
     */
    protected function assertReferences(array $foreignKeys, array $existing, array $created, array $hidden): void
    {
        foreach ($foreignKeys as $foreignKey) {
            $referenced = (string) $foreignKey['referencesTable'];

            if (in_array($referenced, $hidden, true) || (! in_array($referenced, $existing, true) && ! in_array($referenced, $created, true))) {
                throw new RuntimeException(__('erd.assistant.errors.reference_missing', ['table' => $referenced]));
            }
        }
    }

    /**
     * The tables created first, each one after the tables it references; a table in a cycle is created without
     * the keys to the tables created after it, added once they exist. The tables dropped come last.
     *
     * @param  list<SchemaChange>  $changes
     * @return list<SchemaChange>
     */
    protected function ordered(array $changes): array
    {
        $creates = array_values(array_filter($changes, fn (SchemaChange $change): bool => $change->kind === SchemaChange::CREATE && $change->isValid()));
        $pendingNames = array_map(fn (SchemaChange $change): string => (string) $change->table, $creates);
        $sorted = [];

        while ($creates !== []) {
            $pending = fn (SchemaChange $change): array => array_values(array_filter(
                $change->design['foreignKeys'] ?? [],
                fn (array $foreignKey): bool => $foreignKey['referencesTable'] !== $change->table && in_array($foreignKey['referencesTable'], $pendingNames, true),
            ));

            $next = array_find_key($creates, fn (SchemaChange $change): bool => $pending($change) === []);

            if ($next === null) {
                // A cycle: the first table waits for no one, its keys to the others are added afterwards.
                $next = array_key_first($creates);
                $creates[$next] = $creates[$next]->withDeferredForeignKeys($pending($creates[$next]));
            }

            $sorted[] = $creates[$next];
            $pendingNames = array_values(array_diff($pendingNames, [$creates[$next]->table]));
            unset($creates[$next]);
        }

        $rank = fn (SchemaChange $change): int => match ($change->kind) {
            SchemaChange::CREATE => 0,
            SchemaChange::ALTER => 1,
            SchemaChange::RAW => 2,
            SchemaChange::DROP => 3,
        };
        $others = array_values(array_filter($changes, fn (SchemaChange $change): bool => $change->kind !== SchemaChange::CREATE || ! $change->isValid()));
        usort($others, fn (SchemaChange $a, SchemaChange $b): int => $rank($a) <=> $rank($b));

        return [...$sorted, ...$others];
    }

    /**
     * @return list<string>
     */
    protected function messages(ValidationException $exception): array
    {
        $messages = [];

        foreach ($exception->errors() as $field => $errors) {
            foreach ($errors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        return $messages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listOf(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * @return list<string>
     */
    protected function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
