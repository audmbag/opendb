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
 * Write the SQL the user describes in the console. The statements are only written: the user reads them, then runs them.
 */
#[MaxSteps(8)]
class QueryWriterAgent implements Agent, HasStructuredOutput, HasTools
{
    use AnswersInInterfaceLanguage, Promptable;

    /**
     * @param  list<string>  $tables  the names of the tables the agent may read
     * @param  string  $script  what the console already holds, to build on it when the user refers to it
     */
    public function __construct(
        public DriverContract $driver,
        public array $tables,
        public string $script,
    ) {}

    public function instructions(): Stringable|string
    {
        $tables = implode(', ', $this->tables);
        $script = $this->script === '' ? '(empty)' : $this->script;
        $language = $this->interfaceLanguage();

        return <<<INSTRUCTIONS
        You write SQL queries for a {$this->driver->label()} database, in the SQL console of the user. They describe what they want,
        you write the statements, they read them again and then run them themselves. You run nothing.

        Tables of the database: {$tables}

        Script already in the console:
        {$script}

        Rules:
        - Check the columns of the tables with the FocusTable tool before using them: do not invent any name.
        - Write valid SQL for {$this->driver->label()}, with keywords, types and functions in UPPERCASE, each statement ended by a semicolon.
        - Put the names of tables and columns between the quotes of the engine when they are reserved words or contain special characters.
        - Always limit a read that can return many rows (LIMIT 100 by default).
        - If the request relies on the script already there ("add a filter", "the same for…"), write the new complete version.
        - sql: the statements alone, without a Markdown block or a comment.
        - explanation: one sentence in {$language} that says what the statements do, and warns if they change or delete data.
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
            'sql' => $schema->string()->required(),
            'explanation' => $schema->string()->required(),
        ];
    }
}
