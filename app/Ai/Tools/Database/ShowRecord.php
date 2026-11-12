<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\RecordInspector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShowRecord extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Displays in the interface, as an artifact, the card of a record: all its columns, the records it '
            .'references (outgoing foreign keys, browsable) and the number of records referencing it (incoming '
            .'foreign keys). This tool does not return the values to you: it is only for the rendering. Use it '
            .'when the user wants to SEE a precise record (e.g. "show the card of customer 42", "show me order '
            .'CMD-001"). `tableName` is the exact name of the table; `key` is a `column => value` object '
            .'identifying the record, preferably its primary key (e.g. {"id": 42}), otherwise a unique column '
            .'(e.g. {"reference": "CMD-001"}).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $record = new RecordInspector($this->driver, (string) $request->string('tableName'))
                ->find((array) ($request['key'] ?? []));

            return [
                'table' => $record['table'],
                'key' => $record['key'],
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('The exact name of the table of the record.'),
            'key' => $schema->object()
                ->required()
                ->description('A `column => value` object identifying the record, preferably its primary key (e.g. {"id": 42}).'),
        ];
    }
}
