<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\TableProfiler;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProfileTable extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Displays in the interface, as an artifact, the data profile of a table: for each '
            .'column, the rate of NULL values, the number of distinct values, the minimum and the maximum, the average '
            .'length of the texts and the most frequent values (computed on a sample of '
            .TableProfiler::SAMPLE_SIZE.' rows). This tool does not return these statistics to you: it is only for the '
            .'rendering. Use it when the user wants to SEE the quality or the distribution of the data of a table '
            .'(e.g. "profile the users table", "which columns of orders are badly filled?"). '
            .'The `tableName` parameter is the exact name of the table.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $profiler = new TableProfiler($this->driver, (string) $request->string('tableName'));

            return ['table' => $profiler->table];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('The exact name of the table to profile.'),
        ];
    }
}
