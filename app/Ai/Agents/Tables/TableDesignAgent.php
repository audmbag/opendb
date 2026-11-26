<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Ai\Tools\Tables\ProposeTableDesign;
use App\Database\Contracts\DriverContract;
use App\Services\TableDesigner\ColumnTypeCatalog;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The assistant of the designer of a new table: from a sentence of the user, it fills the form with the name of the
 * table, its columns, its indexes and its foreign keys. It runs nothing: the user accepts or rejects what it proposes,
 * then creates the table.
 */
#[MaxSteps(10)]
class TableDesignAgent implements Agent, HasTools
{
    use AnswersInInterfaceLanguage, Promptable;

    /**
     * Kept for the whole answer, so that the design it holds is read once the agent is done.
     */
    public readonly ProposeTableDesign $proposal;

    /**
     * @param  list<string>  $tables  the names of the tables the agent may read
     * @param  string  $form  what the form of the designer holds, in JSON, to build on it
     */
    public function __construct(
        public DriverContract $driver,
        public array $tables,
        public string $form,
    ) {
        $this->proposal = new ProposeTableDesign($driver);
    }

    public function instructions(): Stringable|string
    {
        $tables = implode(', ', $this->tables) ?: '(none)';
        $types = implode(', ', ColumnTypeCatalog::TYPES);
        $language = $this->interfaceLanguage();

        return <<<INSTRUCTIONS
        You design a new table of a {$this->driver->label()} database, in the form of the table designer of the user.
        They describe the table they want, or a change to the form; you fill the form with ProposeTableDesign. Nothing runs:
        they review your proposal, accept or reject it, then create the table themselves.

        Tables of the database: {$tables}

        What the form holds now (JSON):
        {$this->form}

        How to work:
        - Each call of ProposeTableDesign replaces the whole form: give the complete table, with the name, every column you keep
          from the form, the primary key, the indexes and the foreign keys. Keep what the user did not ask to change.
        - Keep the name of the table given in the form, unless it is empty or the user asks for another one.
        - Follow the conventions of the existing tables (names in the same language and case, plural or singular, id type,
          timestamps…): read one or two of them with FocusTable. For an empty database: a snake_case plural table name,
          an auto-incremented bigInteger "id", "<singular>_id" foreign keys, created_at and updated_at timestamps.
        - Column types: {$types}. A string column is varchar(255) unless you give a length.
        - Add a foreign key for each column that points to another table, and read that table with FocusTable first:
          the column has exactly the type of the column it references (same integer type and, on MySQL and MariaDB,
          same unsigned flag). Only reference tables of the list above, or the new table itself.
        - Add an index on the columns that will be searched or sorted often, and a unique index where values must not repeat.
          A foreign key column needs no extra index.
        - When the tool returns errors, fix them and call it again, until it succeeds.
        - When you cannot propose a table (the request is unclear, or a table of the list already holds what they describe and they
          did not ask for another one), do not call the tool: say why and what they could ask instead.

        Answer in {$language}, in plain text without Markdown, in one or two short sentences: what the proposed table holds and the
        choices worth checking. Never list the columns: the form shows them.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ListTables($this->driver),
            new FocusTable($this->driver),
            $this->proposal,
        ];
    }
}
