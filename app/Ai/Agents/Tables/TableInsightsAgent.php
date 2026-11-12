<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Explain what a table holds and point at what looks wrong in its data, from its structure and the profile of its columns.
 */
class TableInsightsAgent implements Agent, HasStructuredOutput
{
    use AnswersInInterfaceLanguage, Promptable;

    /**
     * @param  list<string>  $columns  the names of the columns of the table
     */
    public function __construct(
        public string $table,
        public array $columns,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a data analyst. You are given the structure of the table "{$this->table}" and the profile of its columns,
        computed on a sample of its rows: rate of NULL, number of distinct values, minimum, maximum, lengths and frequent values.

        Answer in {$this->interfaceLanguage()}, concretely and briefly:
        - summary: two or three sentences about what the table contains and its role in the database, from its columns and its relations.
        - columns: the meaning of each column whose name is not obvious, in a few words. Leave out the obvious columns like id, created_at or updated_at.
        - anomalies: what deserves attention in the data, with the figures of the profile. For example, a column almost always NULL,
          a column unique in practice without a unique index, values out of the expected order of magnitude, a column never used,
          or mixed formats. Do not invent anything the profile does not show. If there is nothing to report, return an empty list.
          severity is warning for a probable problem, info for a simple remark.
        - questions: two or three precise questions that would be useful to ask next about this table to the SQL assistant.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'columns' => $schema->array()->items($schema->object([
                'name' => $schema->string()->enum($this->columns)->required(),
                'meaning' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
            'anomalies' => $schema->array()->items($schema->object([
                'column' => $schema->string()->enum($this->columns)->nullable()->required(),
                'issue' => $schema->string()->required(),
                'severity' => $schema->string()->enum(['info', 'warning'])->required(),
            ])->withoutAdditionalProperties())->required(),
            'questions' => $schema->array()->items($schema->string())->max(3)->required(),
        ];
    }
}
