<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DatabaseSchema extends BaseTool implements Tool
{
    public function __construct(
        protected DriverContract $driver
    ) {}

    public function description(): Stringable|string
    {
        return 'Describes the complete structure of the database: every table, its columns '
            .'and its relations, in a single call. This tool is costly in context and must '
            .'NEVER be used as the starting point of an exploration. '
            .'Keep it for explicit requests of a complete overview or of a global documentation '
            .'of the database (e.g. "show me the whole structure of the database", "document the '
            .'complete schema"). For any request about one or several precise tables, '
            .'use ListTables then FocusTable.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->toJson($this->driver->getSchema(false));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }
}
