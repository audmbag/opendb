<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Read a statement of the SQL editor to fix the error the database gave, explain it, or make it faster. The agent
 * only writes: the user reads its version, then puts it in the script and runs it.
 */
#[MaxSteps(8)]
class QueryReviewerAgent implements Agent, HasStructuredOutput, HasTools
{
    use AnswersInInterfaceLanguage, Promptable;

    public const array MODES = ['fix', 'explain', 'optimize'];

    /**
     * @param  'fix'|'explain'|'optimize'  $mode
     * @param  ?string  $error  the message of the database, to fix
     * @param  ?array<string, mixed>  $plan  the summary of the execution plan, to optimize
     */
    public function __construct(
        public DriverContract $driver,
        public string $mode,
        public string $sql,
        public ?string $error = null,
        public ?array $plan = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $engine = $this->driver->label();
        $language = $this->interfaceLanguage();
        $task = match ($this->mode) {
            'fix' => <<<TASK
            The database refused this statement with the following error:
            {$this->error}

            Find the cause and write the fixed statement. Check the names of the tables and columns with FocusTable.
            explanation: the cause of the error and what you changed, in two or three sentences.
            TASK,
            'explain' => <<<'TASK'
            Explain what this statement does, step by step, for someone who knows SQL poorly: the tables read, the joins,
            the filters, the groupings and what the result returns. Warn if it changes or deletes data.
            sql: empty, unless the statement has an obvious flaw; then write its fixed version.
            TASK,
            'optimize' => <<<TASK
            Make this statement faster without changing its result. Summary of its execution plan:
            {$this->planSummary()}

            Rely on the alerts of the plan. Rewrite the statement if another way of writing it is more efficient, and propose
            the useful indexes (CREATE INDEX …) after it, each one ended by a semicolon: they are not created without the user.
            explanation: what is costly in the plan and what your version and your indexes change.
            sql: empty if the statement is already efficient.
            TASK,
        };

        return <<<INSTRUCTIONS
        You review an SQL statement written in the SQL editor of the user, for a {$engine} database. You run nothing.

        Statement:
        {$this->sql}

        {$task}

        Rules:
        - Write valid SQL for {$engine}, with keywords, types and functions in UPPERCASE, each statement ended by a semicolon.
        - Do not invent any table or column name: check them with FocusTable.
        - sql: the statements alone, without a Markdown block or a comment, or an empty string.
        - explanation: in {$language}, in plain text, without Markdown.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ListTables($this->driver),
            new FocusTable($this->driver),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'explanation' => $schema->string()->required(),
            // An empty string rather than null: every provider supports a required string in its structured output.
            'sql' => $schema->string()->required(),
        ];
    }

    private function planSummary(): string
    {
        return $this->plan === null
            ? '(unavailable: only a SELECT query has a plan)'
            : (string) json_encode($this->plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
