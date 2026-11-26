<?php

namespace App\Ai\Tools\Tables;

use App\Ai\Tools\Concerns\DescribesTableDesigns;
use App\Ai\Tools\Database\BaseTool;
use App\Database\Contracts\DriverContract;
use App\Services\Erd\Assistant\SchemaChange;
use App\Services\Erd\Assistant\SchemaChangePlanner;
use App\Services\TableDesigner\TableDesignReader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The table the assistant of the designer proposes, checked by the rules of the designer and kept to fill its form.
 * Nothing runs: the user accepts or rejects the proposal, then creates the table themselves.
 *
 * @phpstan-import-type TableDesign from TableDesignReader
 */
class ProposeTableDesign extends BaseTool implements Tool
{
    use DescribesTableDesigns;

    /**
     * The last design proposed that follows the rules, null until then.
     *
     * @var TableDesign|null
     */
    public ?array $design = null;

    public function __construct(
        public readonly DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Fills the form of the designer with the complete table: its name, every column, its primary key, its '
            .'indexes and its foreign keys. Nothing runs: the user reviews the proposal, accepts or rejects it, then '
            .'creates the table. The design is checked first: when the result lists errors, fix them and call the tool '
            .'again. The last valid call replaces the whole form.';
    }

    public function handle(Request $request): Stringable|string
    {
        $table = $request->all();
        $autoIncremented = array_filter((array) ($table['columns'] ?? []), static fn (mixed $column): bool => is_array($column) && ($column['autoIncrement'] ?? false));
        $primaryKey = (array) ($table['primaryKey'] ?? []) ?: array_column($autoIncremented, 'name');
        $table['indexes'] = $this->withoutRedundantIndexes((array) ($table['indexes'] ?? []), $primaryKey);

        $plan = app(SchemaChangePlanner::class)->plan($this->driver, [[...$table, 'action' => SchemaChange::CREATE]]);
        $change = $plan->changes[0] ?? null;

        if (! $plan->isValid() || $change?->design === null) {
            return $this->toJson(['success' => false, 'errors' => $plan->errors() ?: [__('erd.assistant.errors.empty')]]);
        }

        $this->design = $change->design;

        return $this->toJson(['success' => true]);
    }

    /**
     * The indexes without the plain ones whose columns start the primary key or another index: the database already
     * reads those columns with it, like `customer_id` with a unique index on `customer_id, product_id`. Of two identical
     * plain indexes, the first is kept.
     *
     * @param  array<int, mixed>  $indexes
     * @param  array<int, mixed>  $primaryKey
     * @return list<mixed>
     */
    private function withoutRedundantIndexes(array $indexes, array $primaryKey): array
    {
        $startsWith = static fn (array $columns, array $prefix): bool => $prefix !== [] && array_slice($columns, 0, count($prefix)) === $prefix;
        $columnsOf = static fn (mixed $index): array => array_values((array) (is_array($index) ? ($index['columns'] ?? []) : []));

        return array_filter($indexes, static function (mixed $index, int $position) use ($indexes, $primaryKey, $startsWith, $columnsOf): bool {
            $columns = $columnsOf($index);

            if (! is_array($index) || ($index['unique'] ?? false) || $columns === []) {
                return true;
            }

            if ($startsWith(array_values($primaryKey), $columns)) {
                return false;
            }

            return ! array_any($indexes, static function (mixed $other, int $otherPosition) use ($columns, $position, $startsWith, $columnsOf): bool {
                $otherColumns = $columnsOf($other);

                return $otherPosition !== $position && $startsWith($otherColumns, $columns)
                    && (count($otherColumns) > count($columns) || ($other['unique'] ?? false) || $otherPosition < $position);
            });
        }, ARRAY_FILTER_USE_BOTH) |> array_values(...);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()->required()->description('Name of the new table.'),
            'columns' => $schema->array()->min(1)->required()->items($schema->object($this->columnSchema($schema)))->description('Every column, in their order.'),
            'primaryKey' => $schema->array()->items($schema->string())->description('The columns of the primary key. Implied by an autoIncrement column.'),
            'indexes' => $schema->array()->items($this->indexSchema($schema))->description('The indexes.'),
            'foreignKeys' => $schema->array()->items($this->foreignKeySchema($schema))->description('The foreign keys.'),
        ];
    }
}
