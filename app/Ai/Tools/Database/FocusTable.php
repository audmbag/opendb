<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class FocusTable extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Describes a specific table in detail: its columns (name, type, nullable, default '
            .'value), its primary key, its indexes and its foreign keys. '
            .'Use it after ListTables, once the exact name of the table is identified. '
            .'The `tableName` parameter is the name of the target table. '
            .'The `withRelatedTables` parameter (boolean) also loads the structure of the tables related '
            .'by a foreign key (in both directions). Set it to true if the request involves a join, '
            .'a filter on a related table, or an understanding of the relational context of the table; '
            .'leave it false if only the structure of this table alone is needed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $tableName = $request->string('tableName');
        $withRelatedTables = $request->boolean('withRelatedTables');

        try {
            $this->validateTable($tableName, $this->driver);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        $schema = $this->driver->focusTable($tableName, $withRelatedTables);

        return $this->toJson($schema);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('The name of the target table.'),
            'withRelatedTables' => $schema->boolean()
                ->default(false)
                ->description('Whether the tables related to the target table must be loaded too.'),
        ];
    }
}
