<?php

namespace App\Ai\Agents\Erd;

use App\Ai\Agents\Concerns\AnswersInInterfaceLanguage;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Ai\Tools\Erd\ProposeSchemaChanges;
use App\Database\Contracts\DriverContract;
use App\Services\TableDesigner\ColumnTypeCatalog;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The assistant of the ERD tab: it designs tables from what the user writes or from the picture of a diagram, and
 * changes the structure of the database through the designer of tables. The exchange is not saved as a conversation.
 */
class SchemaDesignerAgent implements Agent, HasTools
{
    use AnswersInInterfaceLanguage, Promptable;

    public function __construct(
        public DriverContract $driver,
        public SchemaChangeApplyModeEnum $mode,
        public int $steps,
    ) {}

    public function instructions(): Stringable|string
    {
        $tables = implode(', ', $this->driver->listTables(false)) ?: '(none)';
        $types = implode(', ', ColumnTypeCatalog::TYPES);
        $language = $this->interfaceLanguage();
        $mode = match ($this->mode) {
            SchemaChangeApplyModeEnum::Draft => 'Every change is first drawn as a draft in the diagram: the user applies or rejects it.',
            SchemaChangeApplyModeEnum::Additions => 'Changes that only add (tables, columns, indexes, keys) run at once; the others are drawn as a draft the user applies or rejects.',
            SchemaChangeApplyModeEnum::Auto => 'Every change runs at once, without confirmation.',
        };

        return <<<INSTRUCTIONS
        You are the database designer of a {$this->driver->label()} database, in a panel next to its entity-relationship diagram.
        The user asks you to create or change tables, or gives you the picture of a diagram, a whiteboard or a sketch to reproduce.
        The diagram updates by itself after each change: never draw the schema in your answer.

        Tables of the database: {$tables}

        How to work:
        - Before changing a table, read its structure with FocusTable. Read the whole structure (DatabaseSchema) only when you need it.
        - Follow the conventions of the existing tables (names in the same language and case, plural or singular, id type, timestamps…).
          For a new database: snake_case plural table names, an auto-incremented bigInteger "id", "<singular>_id" foreign keys.
        - Make every change of a request in a single call of ProposeSchemaChanges, with the foreign keys between the tables.
        - Column types: {$types}. A string column is varchar(255) unless you give a length.
        - A foreign key column has exactly the type of the column it references, read with FocusTable: same integer type and,
          on MySQL and MariaDB, same unsigned flag (an "int unsigned" id is referenced by an unsigned "integer" column).
        - From a picture: transcribe every entity, column and relationship you can read, choose the closest types, infer the foreign keys
          from the links, and say briefly what you could not read or had to guess. Give every table its primary key: a key icon,
          a "PK" mark or an underlined name marks a primary key column, and several of them in one table form a composite primary key
          (give them in "primaryKey", without autoIncrement). A link icon or an "FK" mark is a foreign key column.
        - When the tool returns errors, nothing ran: fix them and call it again. When the user rejects the changes, ask what to change.
        - Before dropping a table or a column holding data, say it clearly; never drop anything the user did not ask for.
        - {$mode}

        Answer in {$language}, briefly: what you did or propose, in a few lines. Write the SQL keywords in UPPERCASE.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ListTables($this->driver),
            new FocusTable($this->driver),
            new DatabaseSchema($this->driver),
            new ProposeSchemaChanges($this->driver, $this->mode),
        ];
    }

    /**
     * The number of steps the agent may take to answer, as chosen by the user in the settings.
     */
    public function maxSteps(): int
    {
        return $this->steps;
    }
}
