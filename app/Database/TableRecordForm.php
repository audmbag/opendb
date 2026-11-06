<?php

namespace App\Database;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\Schema\ColumnType;
use App\Database\Schema\SnapshotBuilder;
use Illuminate\Validation\Rule;

/**
 * The form used to add a row to a table, generated from the structure of its columns.
 */
class TableRecordForm
{
    /**
     * @var list<array{name: string, type: string, db_type: string, required: bool, nullable: bool, default: ?string, max_length: ?int, options: ?list<string>, unique: bool, references: ?array{table: string, column: string}}>
     */
    protected array $fields;

    /**
     * The column filled by the database on insert, whose value is returned once the row is created.
     */
    protected ?string $autoIncrementColumn = null;

    /**
     * @throws HiddenTableException when the table is hidden from the agent.
     * @throws TableNotFoundException when the table is missing.
     */
    public function __construct(
        protected DriverContract $driver,
        public readonly string $table,
    ) {
        HiddenTableGuard::assertTableAllowed($table, $driver->hiddenTables());

        if (! in_array($table, $driver->listTables(false), true)) {
            throw TableNotFoundException::named($table);
        }

        $this->fields = $this->describeFields();
    }

    /**
     * The fields of the form, one for each column the user may fill.
     *
     * @return list<array{name: string, type: string, db_type: string, required: bool, nullable: bool, default: ?string, max_length: ?int, options: ?list<string>, unique: bool, references: ?array{table: string, column: string}}>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * The validation rules of the submitted values, keyed by column.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $connection = $this->driver->getConnection()->getName();

        return collect($this->fields)->mapWithKeys(fn (array $field) => [$field['name'] => array_values(array_filter([
            $field['required'] ? 'required' : 'nullable',
            ...ColumnType::rules($field['type'], $field['options']),
            $field['max_length'] !== null ? "max:{$field['max_length']}" : null,
            $field['unique'] ? Rule::unique("{$connection}.{$this->table}", $field['name']) : null,
            $field['references'] !== null ? Rule::exists("{$connection}.{$field['references']['table']}", $field['references']['column']) : null,
        ]))])->all();
    }

    /**
     * Convert validated values to what the database expects, leaving out the empty optional ones so their default applies.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function normalize(array $values): array
    {
        return collect($this->fields)
            ->filter(fn (array $field) => array_key_exists($field['name'], $values) && $values[$field['name']] !== null)
            ->mapWithKeys(fn (array $field) => [$field['name'] => ColumnType::normalize($field['type'], $values[$field['name']])])
            ->all();
    }

    /**
     * Insert the given normalized values and return them, with the generated identifier when the table has one.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function insert(array $values): array
    {
        $query = $this->driver->getConnection()->table($this->table);

        if ($this->autoIncrementColumn === null) {
            $query->insert($values);

            return $values;
        }

        return [$this->autoIncrementColumn => $query->insertGetId($values, $this->autoIncrementColumn), ...$values];
    }

    /**
     * @return list<array{name: string, type: string, db_type: string, required: bool, nullable: bool, default: ?string, max_length: ?int, options: ?list<string>, unique: bool, references: ?array{table: string, column: string}}>
     */
    protected function describeFields(): array
    {
        $description = app(SnapshotBuilder::class)->describe($this->driver->getConnection(), $this->table);

        $uniqueColumns = collect($description['indexes'])
            ->filter(fn (array $index) => $index['unique'] && ! $index['primary'] && count($index['columns']) === 1)
            ->map(fn (array $index) => $index['columns'][0])
            ->all();

        $references = collect($description['foreign_keys'])
            ->filter(fn (array $foreignKey) => count($foreignKey['columns']) === 1)
            ->mapWithKeys(fn (array $foreignKey) => [$foreignKey['columns'][0] => [
                'table' => $foreignKey['foreign_table'],
                'column' => $foreignKey['foreign_columns'][0],
            ]])
            ->all();

        $fields = [];

        foreach ($description['columns'] as $column) {
            if ($column['auto_increment'] ?? false) {
                $this->autoIncrementColumn = $column['name'];

                continue;
            }

            if (filled($column['generation'] ?? null)) {
                continue;
            }

            $type = ColumnType::of($column);
            $default = ColumnType::defaultValue($column['default'] ?? null);

            $fields[] = [
                'name' => $column['name'],
                'type' => $type,
                'db_type' => $column['type'],
                'required' => ! $column['nullable'] && $default === null,
                'nullable' => (bool) $column['nullable'],
                'default' => $default,
                'max_length' => in_array($type, ['string', 'uuid'], true) ? ColumnType::maxLength($column['type']) : null,
                'options' => $type === 'enum' ? ColumnType::enumOptions($column['type']) : null,
                'unique' => in_array($column['name'], $uniqueColumns, true),
                'references' => $references[$column['name']] ?? null,
            ];
        }

        return $fields;
    }
}
