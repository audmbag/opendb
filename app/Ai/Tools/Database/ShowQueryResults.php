<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Database\SelectQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShowQueryResults extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Displays the result of a SELECT query in an interactive table of the interface (sorting, pagination, '
            .'CSV export), as an artifact. This tool returns NO row to you: only the columns and the total number '
            .'of rows. Use it when the user wants to SEE, browse or export data (e.g. "show me the orders of the '
            .'week", "list the inactive customers"), especially when the result can exceed a few dozen rows. Do '
            .'not add a LIMIT: the interface paginates. If you must analyze the values yourself to answer, use '
            .'ReadQuery instead. The `title` parameter is a short title displayed above the table.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $query = new SelectQuery($this->driver, (string) $request['query'], QueryBindings::from($request['bindings'] ?? null));

            return [
                'title' => $request['title'] ?? null,
                'columns' => $query->columns(),
                'totalRows' => $query->count(),
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->selectQuerySchema($schema),
            'title' => $schema->string()
                ->nullable()
                ->description('Short title displayed above the table (e.g. "Orders of the week").'),
        ];
    }
}
