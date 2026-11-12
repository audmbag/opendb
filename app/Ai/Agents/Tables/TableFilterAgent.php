<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use App\Services\TableBrowser\FilterOperator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Turn a sentence of the user into the filters of the grid. It writes no SQL: only columns of the table and
 * operators of the browser can come out, and the filters are checked again before being shown.
 */
class TableFilterAgent implements Agent, HasStructuredOutput
{
    use AnswersInInterfaceLanguage, Promptable;

    /**
     * @param  list<array{name: string, kind: string, db_type: string, nullable: bool, options: ?list<string>, references: ?array{table: string, column: string}}>  $columns
     */
    public function __construct(
        public string $table,
        public array $columns,
        public string $today,
    ) {}

    public function instructions(): Stringable|string
    {
        $columns = implode("\n", array_map(fn (array $column): string => '- '.$column['name'].' : '.$column['kind'].' ('.$column['db_type'].')'
            .($column['nullable'] ? ', can be NULL' : '')
            .($column['options'] !== null ? ', possible values: '.implode(', ', $column['options']) : '')
            .($column['references'] !== null ? ", references {$column['references']['table']}.{$column['references']['column']}" : ''), $this->columns));
        $language = $this->interfaceLanguage();

        return <<<INSTRUCTIONS
        You turn a request of the user, written in natural language, into filters on the table "{$this->table}" of a database client.
        Today is {$this->today}.

        Columns of the table:
        {$columns}

        Available operators:
        - = != > >= < <=: comparison with a value
        - contains, not_contains, starts_with, ends_with: text search, ignoring the case
        - in: the value is a comma-separated list
        - is_null, is_not_null: without a value (set null)

        Rules:
        - Only use the listed columns and operators. Do not guess a column that does not exist.
        - Write the dates in the YYYY-MM-DD format and the dates with a time in the YYYY-MM-DD HH:MM:SS format.
          A period becomes two filters (>= start and < end) combined with match = all.
        - Write the booleans 1 or 0.
        - match = all when every condition must be true, any when a single one is enough. You cannot mix both.
        - If the request cannot be expressed with these filters (join, aggregate, computation), return an empty list
          and explain why in explanation, suggesting to ask it in the chat.
        - explanation is one short sentence, in {$language}, that describes the filter without repeating the technical names of the operators.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'match' => $schema->string()->enum(['all', 'any'])->required(),
            'conditions' => $schema->array()->items($schema->object([
                'column' => $schema->string()->enum(array_column($this->columns, 'name'))->required(),
                'operator' => $schema->string()->enum(FilterOperator::values())->required(),
                'value' => $schema->string()->nullable()->required(),
            ])->withoutAdditionalProperties())->max(10)->required(),
            'explanation' => $schema->string()->required(),
        ];
    }
}
