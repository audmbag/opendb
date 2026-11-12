<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * The rules a design of the designer follows, whether it comes from the form of the designer or from the assistant
 * of the diagram, and the design they give once its values are normalized.
 *
 * @phpstan-import-type TableDesign from TableDesignReader
 */
class TableDesignValidator
{
    /**
     * A name written without quotes by every engine. An existing name the user keeps is accepted as it is.
     */
    public const string IDENTIFIER = '/^[\pL_][\pL\pN_$]*$/u';

    public const array FOREIGN_KEY_ACTIONS = ['cascade', 'restrict', 'set null', 'set default', 'no action'];

    /**
     * The design checked and normalized.
     *
     * @param  array<string, mixed>  $input
     * @param  ?string  $table  the name the table has in the database, null for a new one
     * @return TableDesign
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?string $table): array
    {
        $validator = ValidatorFactory::make($input, $this->rules($input, $table), $this->messages());
        $validator->after($this->after($input));

        return $this->normalize($validator->validate());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(array $input, ?string $table): array
    {
        $columnNames = $this->columnNames($input);
        $originalOf = fn (string $attribute): mixed => data_get($input, str_replace('.name', '.original', $attribute));

        return [
            'name' => ['required', 'string', 'max:64', $this->identifier(fn (): ?string => $table)],
            'columns' => ['required', 'array', 'min:1', 'max:500'],
            'columns.*.original' => ['nullable', 'string'],
            'columns.*.changed' => ['boolean'],
            'columns.*.name' => ['required', 'string', 'max:64', 'distinct:ignore_case', $this->identifier(fn (string $attribute): ?string => is_string($originalOf($attribute)) ? $originalOf($attribute) : null)],
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
            'primaryKey.*' => ['string', 'distinct', Rule::in($columnNames)],
            'indexes' => ['array'],
            'indexes.*.original' => ['nullable', 'string'],
            'indexes.*.name' => ['nullable', 'string', 'max:64'],
            'indexes.*.columns' => ['required', 'array', 'min:1'],
            'indexes.*.columns.*' => ['string', Rule::in($columnNames)],
            'indexes.*.unique' => ['boolean'],
            'foreignKeys' => ['array'],
            'foreignKeys.*.original' => ['nullable', 'string'],
            'foreignKeys.*.name' => ['nullable', 'string', 'max:64'],
            'foreignKeys.*.columns' => ['required', 'array', 'min:1'],
            'foreignKeys.*.columns.*' => ['string', Rule::in($columnNames)],
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
            'columns.*.name.distinct' => __('requests.design.distinct'),
            'columns.*.options.required_if' => __('requests.design.options'),
            'columns.*.default.required_if' => __('requests.design.default_expression'),
            'columns.*.scale.lte' => __('requests.design.scale'),
            'columns.required' => __('requests.design.columns'),
        ];
    }

    /**
     * The checks that span several fields.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, Closure(Validator): void>
     */
    public function after(array $input): array
    {
        return [function (Validator $validator) use ($input): void {
            $columns = collect((array) ($input['columns'] ?? []))->filter(fn (mixed $column): bool => is_array($column));
            $autoIncrement = $columns->filter(fn (array $column): bool => (bool) ($column['autoIncrement'] ?? false));

            if ($autoIncrement->count() > 1) {
                $validator->errors()->add('columns', __('requests.design.single_auto_increment'));
            }

            foreach ($autoIncrement as $index => $column) {
                if (! in_array($column['type'] ?? null, ColumnTypeCatalog::INTEGER_TYPES, true)) {
                    $validator->errors()->add("columns.{$index}.autoIncrement", __('requests.design.integer_auto_increment'));
                }

                if (array_diff((array) ($input['primaryKey'] ?? []), [$column['name'] ?? null]) !== []) {
                    $validator->errors()->add('primaryKey', __('requests.design.auto_increment_key'));
                }
            }

            foreach ((array) ($input['foreignKeys'] ?? []) as $index => $foreignKey) {
                if (count((array) ($foreignKey['columns'] ?? [])) !== count((array) ($foreignKey['referencesColumns'] ?? []))) {
                    $validator->errors()->add("foreignKeys.{$index}.referencesColumns", __('requests.design.referenced_columns'));
                }
            }

            foreach ($columns as $index => $column) {
                if (($column['type'] ?? null) === 'custom' && (($column['original'] ?? null) === null || ($column['changed'] ?? false))) {
                    $validator->errors()->add("columns.{$index}.type", __('requests.design.type'));
                }
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $design  the validated values
     * @return TableDesign
     */
    public function normalize(array $design): array
    {
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
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    protected function columnNames(array $input): array
    {
        return array_values(array_filter(array_map(fn (mixed $column): mixed => is_array($column) ? ($column['name'] ?? null) : null, (array) ($input['columns'] ?? [])), 'is_string'));
    }

    /**
     * @param  Closure(string): ?string  $kept  the name the element already has in the database, if any
     */
    protected function identifier(Closure $kept): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($kept): void {
            if (is_string($value) && $value !== $kept($attribute) && ! preg_match(self::IDENTIFIER, $value)) {
                $fail(__('requests.identifier'));
            }
        };
    }
}
