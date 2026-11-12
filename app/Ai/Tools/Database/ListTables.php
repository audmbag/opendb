<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTables extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Lists every table of the database with a light overview of each '
            .'(number of columns, presence of a primary key, number of foreign keys), '
            .'without detailing their columns or relations. '
            .'Use it first, before any other schema tool, to spot the tables '
            .'relevant to the request of the user. '
            .'Once the target table or tables are identified, use FocusTable to get their detail.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->toJson($this->driver->listTables());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }
}
