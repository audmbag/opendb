<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ShowSchemaDiagram extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Displays a visual diagram of the database schema in the interface, as an artifact. This tool returns '
            .'NO data to analyze: only use it when the user explicitly asks to SEE a diagram or a visual schema.';
    }

    public function handle(Request $request): Stringable|string
    {
        $focusedTable = $request['focusedTable'] ?? [];

        try {
            $validatedFocusedTable = $this->validateAndGetTables($focusedTable);
        } catch (RuntimeException $e) {
            return $this->toJson(['success' => false, 'error' => $e->getMessage()]);
        }

        return $this->toJson(['success' => true, ...$validatedFocusedTable]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'focusedTable' => $schema->object(fn ($schema) => [
                'tableName' => $schema->string()->required()->description('The name of the target table.'),
                'withRelatedTables' => $schema->boolean()->required()->description('Whether the tables related by a foreign key, in both directions, must also appear in the diagram.'),
            ])
                ->nullable()
                ->description('A nullable object which, when set, centers the diagram on a single table. Leave it null to display the complete diagram of the database.'),
        ];
    }

    private function validateAndGetTables(array $focusedTable): array
    {
        if (empty($focusedTable)) {
            return [
                'focusedTable' => null,
                'withRelatedTables' => false,
                'displayedTables' => $listTables = $this->driver->listTables(false),
                'displayedTablesCount' => count($listTables),
            ];
        }

        if (! isset($focusedTable['tableName'])) {
            throw new RuntimeException('When `focusedTable` is set, it must hold the `tableName` key with the exact name of the target table.');
        }

        if (! isset($focusedTable['withRelatedTables'])) {
            throw new RuntimeException('When `focusedTable` is set with a `tableName`, it must also hold the `withRelatedTables` key with a boolean value.');
        }

        $table = $focusedTable['tableName'];
        $withRelatedTables = (bool) $focusedTable['withRelatedTables'];
        $displayedTables = [];

        $this->validateTable($table, $this->driver);

        if ($withRelatedTables) {
            $tables = $this->driver->focusTable($table);

            $displayedTables = array_map(fn (array $table): string => $table['name'], $tables);
        }

        return [
            'focusedTable' => $table,
            'withRelatedTables' => $withRelatedTables,
            'displayedTables' => $displayedTables,
            'displayedTablesCount' => count($displayedTables),
        ];
    }
}
