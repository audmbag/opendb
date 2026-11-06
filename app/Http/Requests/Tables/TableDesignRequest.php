<?php

namespace App\Http\Requests\Tables;

use App\Services\TableDesigner\ColumnTypeCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A table as the designer describes it: its name, its columns, its primary key, its indexes and its foreign keys.
 * The elements already in the database keep their name in `original`, the new ones have none.
 *
 * @phpstan-import-type TableDesign from \App\Services\TableDesigner\TableDesignReader
 */
class TableDesignRequest extends FormRequest
{
    /**
     * A name written without quotes by every engine. An existing name the user keeps is accepted as it is.
     */
    public const string IDENTIFIER = '/^[\pL_][\pL\pN_$]*$/u';

    public const array FOREIGN_KEY_ACTIONS = ['cascade', 'restrict', 'set null', 'set default', 'no action'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $table = $this->route('table');

        return [
            'name' => ['required', 'string', 'max:64', $this->identifier(fn (): ?string => is_string($table) ? $table : null)],
            'columns' => ['required', 'array', 'min:1', 'max:500'],
            'columns.*.original' => ['nullable', 'string'],
            'columns.*.changed' => ['boolean'],
            'columns.*.name' => ['required', 'string', 'max:64', 'distinct:ignore_case', $this->identifier(fn (string $attribute): ?string => $this->input(str_replace('.name', '.original', $attribute)))],
            'columns.*.type' => ['required', Rule::in([...ColumnTypeCatalog::TYPES, 'custom'])],
            'columns.*.length' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'columns.*.precision' => ['nullable', 'integer', 'min:1', 'max:65'],
            'columns.*.scale' => ['nullable', 'integer', 'min:0', 'max:30', 'lte:columns.*.precision'],
            'columns.*.unsigned' => ['boolean'],
            'columns.*.nullable' => ['boolean'],
            'columns.*.defaultKind' => ['required', Rule::in(ColumnTypeCatalog::DEFAULT_KINDS)],
            'columns.*.default' => ['nullable', 'string', 'max:1000', 'required_if:columns.*.defaultKind,expression'],
            'columns.*.autoIncrement' => ['boolean'],
            'columns.*.options' => ['array', 'required_if:columns.*.type,enum'],
            'columns.*.options.*' => ['string', 'max:255'],
            'primaryKey' => ['array'],
            'primaryKey.*' => ['string', 'distinct', Rule::in($this->columnNames())],
            'indexes' => ['array'],
            'indexes.*.original' => ['nullable', 'string'],
            'indexes.*.name' => ['nullable', 'string', 'max:64'],
            'indexes.*.columns' => ['required', 'array', 'min:1'],
            'indexes.*.columns.*' => ['string', Rule::in($this->columnNames())],
            'indexes.*.unique' => ['boolean'],
            'foreignKeys' => ['array'],
            'foreignKeys.*.original' => ['nullable', 'string'],
            'foreignKeys.*.name' => ['nullable', 'string', 'max:64'],
            'foreignKeys.*.columns' => ['required', 'array', 'min:1'],
            'foreignKeys.*.columns.*' => ['string', Rule::in($this->columnNames())],
            'foreignKeys.*.referencesTable' => ['required', 'string'],
            'foreignKeys.*.referencesColumns' => ['required', 'array', 'min:1'],
            'foreignKeys.*.referencesColumns.*' => ['string'],
            'foreignKeys.*.onDelete' => ['nullable', Rule::in(self::FOREIGN_KEY_ACTIONS)],
            'foreignKeys.*.onUpdate' => ['nullable', Rule::in(self::FOREIGN_KEY_ACTIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'columns.*.name.distinct' => 'Deux colonnes portent ce nom.',
            'columns.*.options.required_if' => 'Une énumération a besoin d\'au moins une valeur.',
            'columns.*.default.required_if' => 'Saisissez l\'expression SQL de la valeur par défaut.',
            'columns.*.scale.lte' => 'L\'échelle ne peut dépasser la précision.',
            'columns.required' => 'Une table a besoin d\'au moins une colonne.',
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $columns = collect($this->input('columns', []));
            $autoIncrement = $columns->filter(fn ($column): bool => (bool) ($column['autoIncrement'] ?? false));

            if ($autoIncrement->count() > 1) {
                $validator->errors()->add('columns', 'Une table ne peut avoir qu\'une colonne auto-incrémentée.');
            }

            foreach ($autoIncrement as $index => $column) {
                if (! in_array($column['type'] ?? null, ColumnTypeCatalog::INTEGER_TYPES, true)) {
                    $validator->errors()->add("columns.{$index}.autoIncrement", 'Seule une colonne entière peut être auto-incrémentée.');
                }

                if (array_diff((array) $this->input('primaryKey', []), [$column['name'] ?? null]) !== []) {
                    $validator->errors()->add('primaryKey', 'La colonne auto-incrémentée est à elle seule la clé primaire.');
                }
            }

            foreach ((array) $this->input('foreignKeys', []) as $index => $foreignKey) {
                if (count($foreignKey['columns'] ?? []) !== count($foreignKey['referencesColumns'] ?? [])) {
                    $validator->errors()->add("foreignKeys.{$index}.referencesColumns", 'Choisissez autant de colonnes référencées que de colonnes.');
                }
            }

            foreach ($columns as $index => $column) {
                if (($column['type'] ?? null) === 'custom' && (($column['original'] ?? null) === null || ($column['changed'] ?? false))) {
                    $validator->errors()->add("columns.{$index}.type", 'Choisissez un type pour cette colonne.');
                }
            }
        }];
    }

    /**
     * @return TableDesign
     */
    public function design(): array
    {
        $design = $this->validated();

        return [
            'name' => $design['name'],
            'columns' => array_map(fn (array $column): array => [
                'original' => $column['original'] ?? null,
                'changed' => (bool) ($column['changed'] ?? false),
                'name' => $column['name'],
                'type' => $column['type'],
                'length' => isset($column['length']) ? (int) $column['length'] : null,
                'precision' => isset($column['precision']) ? (int) $column['precision'] : null,
                'scale' => isset($column['scale']) ? (int) $column['scale'] : null,
                'unsigned' => (bool) ($column['unsigned'] ?? false),
                'nullable' => (bool) ($column['nullable'] ?? false),
                'defaultKind' => $column['defaultKind'],
                'default' => $column['default'] ?? null,
                'autoIncrement' => (bool) ($column['autoIncrement'] ?? false),
                'options' => array_values($column['options'] ?? []),
            ], array_values($design['columns'])),
            'primaryKey' => array_values($design['primaryKey'] ?? []),
            'indexes' => array_map(fn (array $index): array => [
                'original' => $index['original'] ?? null,
                'name' => $index['name'] ?? null,
                'columns' => array_values($index['columns']),
                'unique' => (bool) ($index['unique'] ?? false),
            ], array_values($design['indexes'] ?? [])),
            'foreignKeys' => array_map(fn (array $foreignKey): array => [
                'original' => $foreignKey['original'] ?? null,
                'name' => $foreignKey['name'] ?? null,
                'columns' => array_values($foreignKey['columns']),
                'referencesTable' => $foreignKey['referencesTable'],
                'referencesColumns' => array_values($foreignKey['referencesColumns']),
                'onDelete' => $foreignKey['onDelete'] ?? null,
                'onUpdate' => $foreignKey['onUpdate'] ?? null,
            ], array_values($design['foreignKeys'] ?? [])),
        ];
    }

    /**
     * @return list<string>
     */
    protected function columnNames(): array
    {
        return array_values(array_filter(array_map(fn ($column): mixed => is_array($column) ? ($column['name'] ?? null) : null, (array) $this->input('columns', [])), 'is_string'));
    }

    /**
     * @param  Closure(string): ?string  $kept  the name the element already has in the database, if any
     */
    protected function identifier(Closure $kept): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kept): void {
            if (is_string($value) && $value !== $kept($attribute) && ! preg_match(self::IDENTIFIER, $value)) {
                $fail('Un nom commence par une lettre ou « _ », puis ne contient que des lettres, des chiffres, « _ » ou « $ ».');
            }
        };
    }
}
