<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Suggest the indexes that would speed up the view of a table, from the plan of its query. The indexes are never created.
 */
class TableIndexAdvisorAgent implements Agent, HasStructuredOutput
{
    use AnswersInInterfaceLanguage, Promptable;

    public function __construct(
        public string $table,
        public string $driver,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are an expert in the performance of {$this->driver} databases. You are given the query that shows a view of the table "{$this->table}"
        (its filters and its sort), the summary of its execution plan, the detected alerts and the indexes that already exist.

        Answer in {$this->interfaceLanguage()}:
        - indexes: the indexes that would really speed up this query, from the most useful to the least useful, three at most.
          statement is the complete CREATE INDEX statement, valid for {$this->driver}, with an explicit name
          and its keywords in UPPERCASE.
          Do not propose one that duplicates an existing index or that starts with the same columns. Put first in the index
          the columns filtered by equality, then those of the comparisons, then those of the sort.
          impact is high when the plan scans a whole large table or sorts without an index, medium or low otherwise.
          reason explains in one sentence what the index avoids in the plan.
        - note: one sentence about the current plan. If the query is already efficient or the table is too small for an index to matter,
          say so and return an empty list of indexes.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'indexes' => $schema->array()->items($schema->object([
                'statement' => $schema->string()->required(),
                'columns' => $schema->array()->items($schema->string())->required(),
                'reason' => $schema->string()->required(),
                'impact' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
            ])->withoutAdditionalProperties())->max(3)->required(),
            'note' => $schema->string()->required(),
        ];
    }
}
