<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Database\ReadOnlyQueryGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ReadQuery extends BaseTool implements Tool
{
    public function __construct(
        private readonly DriverContract $driver,
        private readonly int $maxRows = 200,
    ) {}

    public function description(): Stringable|string
    {
        return 'Runs a read-only SQL query (SELECT, WITH, EXPLAIN, SHOW or DESCRIBE) '
            .'on the current database and returns the rows obtained. '
            ."For a SELECT query, always add a LIMIT of {$this->maxRows} and tell the user clearly "
            .'that the result is truncated when the number of returned rows equals this limit.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) $request['query']);

        try {
            ReadOnlyQueryGuard::assertReadOnly($sql);

            $rows = $this->driver->executeReadOnly($sql, QueryBindings::from($request['bindings'] ?? null));

            $rows = array_slice($rows, 0, $this->maxRows);
        } catch (QueryException $e) {
            return $this->error($this->formatQueryError($e));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        if (empty($rows)) {
            return '[]';
        }

        $json = $this->toJson($rows);

        return count($rows) === $this->maxRows
            ? $json."\n\n(Result truncated to {$this->maxRows} rows.)"
            : $json;
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->getSchemaForQueryByAccessMode(AgentAccessModeEnum::ReadOnly, $schema);
    }
}
