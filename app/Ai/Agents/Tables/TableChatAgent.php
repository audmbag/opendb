<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ReadQuery;
use App\Database\Contracts\DriverContract;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Answer a quick question about the table shown in the Tables tab. The exchange is not saved as a conversation,
 * and the agent only reads the database.
 */
#[MaxSteps(8)]
class TableChatAgent implements Agent, HasTools
{
    use AnswersInInterfaceLanguage, Promptable;

    /**
     * @param  array<string, mixed>  $definition  the columns, keys and indexes of the table
     * @param  string  $view  the statement reading the rows the user is looking at, with its filters and sort
     */
    public function __construct(
        public DriverContract $driver,
        public string $table,
        public array $definition,
        public string $view,
    ) {}

    public function instructions(): Stringable|string
    {
        $definition = json_encode($this->definition, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $language = $this->interfaceLanguage();

        return <<<INSTRUCTIONS
        You are the SQL assistant of a {$this->driver->label()} database. The user is looking at the table "{$this->table}" in the table explorer
        and asks you a quick question about it, in a small panel next to the data.

        Structure of the table:
        {$definition}

        Query of the rows they have before their eyes, with its filters and its sort:
        {$this->view}

        Rules:
        - Answer in {$language}, briefly and directly: a few sentences, a small list or a small Markdown table.
        - "These rows", "the displayed rows" or "my selection" mean the result of the query above.
        - To answer with figures, run a read query with the provided tool rather than guessing them. Always limit the rows read.
        - You cannot change anything in the database. If the user asks for a change, give them the query to run in a ```sql block
          and tell them they can run it from the SQL console or the chat.
        - Show the SQL query used when it helps to understand the answer.
        - Always write the SQL keywords, types and functions in UPPERCASE (SELECT, FROM, WHERE, COUNT…).
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ReadQuery($this->driver, maxRows: 100),
            new ProfileTable($this->driver),
            new FocusTable($this->driver),
            new DatabaseSchema($this->driver),
        ];
    }
}
