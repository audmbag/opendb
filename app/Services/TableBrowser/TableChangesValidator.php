<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Database\Schema\ColumnType;
use App\Services\TableBrowser\Data\TableChange;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Check the changes of the user against the columns of the table, and convert their values to what the database expects.
 * The errors are keyed by change, like `changes.2.values.email`, so the grid can point at the faulty cell.
 */
class TableChangesValidator
{
    /**
     * Kinds of column where an empty string is a value of its own, distinct from NULL.
     */
    protected const array TEXTUAL_KINDS = ['string', 'text', 'enum'];

    public function __construct(protected TableColumns $columns) {}

    /**
     * @param  list<array{type: 'insert'|'update'|'delete', key?: array<string, mixed>, values?: array<string, mixed>}>  $changes
     * @return list<TableChange>
     *
     * @throws ValidationException
     */
    public function validate(DriverContract $driver, string $table, array $changes): array
    {
        ['primaryKey' => $primaryKey, 'columns' => $columns] = $this->columns->describe($driver, $table);
        $columns = array_column($columns, null, 'name');
        $connection = $driver->getConnection()->getName();

        $errors = [];
        $validated = [];

        foreach (array_values($changes) as $index => $change) {
            $key = $change['key'] ?? [];
            $values = $change['values'] ?? [];

            if ($change['type'] !== 'insert') {
                if ($primaryKey === []) {
                    $errors["changes.{$index}"][] = __('tables.errors.no_primary_key', ['table' => $table]);

                    continue;
                }

                if (array_diff($primaryKey, array_keys($key)) !== [] || array_diff(array_keys($key), $primaryKey) !== []) {
                    $errors["changes.{$index}"][] = __('tables.errors.key_required', ['columns' => implode(', ', $primaryKey)]);

                    continue;
                }
            }

            if ($change['type'] === 'delete') {
                $validated[] = new TableChange('delete', $key);

                continue;
            }

            $editable = array_filter($columns, fn (array $column): bool => $column['editable'] && ! ($change['type'] === 'update' && $column['primary']));
            $refused = array_diff(array_keys($values), array_keys($editable));

            foreach ($refused as $column) {
                $errors["changes.{$index}.values.{$column}"][] = isset($columns[$column])
                    ? __('tables.errors.column_not_editable', ['column' => $column])
                    : __('tables.errors.unknown_column', ['column' => $column, 'table' => $table]);
            }

            if ($refused !== []) {
                continue;
            }

            if ($change['type'] === 'update' && $values === []) {
                $errors["changes.{$index}"][] = __('tables.errors.empty_change');

                continue;
            }

            foreach ($values as $column => $value) {
                if ($value === '' && ! in_array($columns[$column]['kind'], self::TEXTUAL_KINDS, true)) {
                    $values[$column] = null;
                }
            }

            $checked = $change['type'] === 'insert'
                ? array_filter($editable, fn (array $column): bool => ! $column['auto_increment'] || array_key_exists($column['name'], $values))
                : array_intersect_key($editable, $values);

            $validator = Validator::make(
                $values,
                array_map(fn (array $column): array => $this->rules($column, $connection, $table, $change['type'] === 'update' ? $key : null), $checked),
                attributes: array_combine(array_keys($checked), array_keys($checked)),
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $column => $messages) {
                    $errors["changes.{$index}.values.{$column}"] = $messages;
                }

                continue;
            }

            foreach ($values as $column => $value) {
                $values[$column] = ColumnType::normalize($columns[$column]['kind'], $value);
            }

            $validated[] = new TableChange($change['type'], $key, $values);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    /**
     * The rules of a value of the column. A column that may not be NULL accepts an empty string only when it holds text.
     *
     * @param  array{name: string, kind: string, nullable: bool, default: ?string, auto_increment: bool, max_length: ?int, options: ?list<string>, unique: bool, references: ?array{table: string, column: string}}  $column
     * @param  array<string, mixed>|null  $key  the primary key of the updated row, null for an insert
     * @return list<mixed>
     */
    protected function rules(array $column, string $connection, string $table, ?array $key): array
    {
        $optional = $key === null && ($column['default'] !== null || $column['auto_increment'] || $column['nullable']);

        $unique = $column['unique'] ? Rule::unique("{$connection}.{$table}", $column['name']) : null;

        if ($unique !== null && $key !== null && count($key) === 1) {
            $unique->ignore(array_first($key), array_key_first($key));
        }

        return array_values(array_filter([
            $optional ? 'sometimes' : null,
            match (true) {
                $column['nullable'] => 'nullable',
                in_array($column['kind'], self::TEXTUAL_KINDS, true) => 'present',
                default => 'required',
            },
            ...ColumnType::rules($column['kind'], $column['options']),
            $column['max_length'] !== null ? "max:{$column['max_length']}" : null,
            $unique,
            $column['references'] !== null ? Rule::exists("{$connection}.{$column['references']['table']}", $column['references']['column']) : null,
        ]));
    }
}
