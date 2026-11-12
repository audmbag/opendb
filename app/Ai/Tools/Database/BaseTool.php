<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Tools\ToolError;
use App\Database\Contracts\DriverContract;
use App\Database\HiddenTableGuard;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema as JsonSchemaContract;
use Illuminate\Database\QueryException;
use Illuminate\JsonSchema\JsonSchema;
use RuntimeException;

class BaseTool
{
    protected function formatQueryError(QueryException $e): string
    {
        return $e->getPrevious()?->getMessage() ?? $e->getMessage();
    }

    /**
     * The answer of a call that failed: plain text for the agent, an error for the MCP clients.
     */
    protected function error(string $message): ToolError
    {
        return new ToolError($message);
    }

    protected function toJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * The output of a tool rendered in the chat interface: the metadata returned by the callback, or the error that prevents the rendering.
     *
     * @param  Closure(): array<string, mixed>  $callback
     */
    protected function artifactOutput(Closure $callback): string
    {
        try {
            return $this->toJson(['success' => true, ...$callback()]);
        } catch (QueryException $e) {
            return $this->toJson(['success' => false, 'error' => $this->formatQueryError($e)]);
        } catch (RuntimeException $e) {
            return $this->toJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The parameters of a SELECT query whose rows are displayed in the chat interface.
     *
     * @return array<string, mixed>
     */
    protected function selectQuerySchema(JsonSchemaContract $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('A single SELECT (or WITH … SELECT) SQL query, prepared with named bindings, without several semicolons.')
                ->required(),
            'bindings' => $schema->object()
                ->description('Key-value object holding the parameters (e.g. {"id": 1, "status": "active"}). Empty object if there is no parameter.')
                ->required(),
        ];
    }

    /**
     * @throws RuntimeException if the table is hidden from the agent or does not exist in the database.
     */
    protected function validateTable(string $tableName, DriverContract $driver): array
    {
        HiddenTableGuard::assertTableAllowed($tableName, $driver->hiddenTables());

        $listTables = $driver->listTables(false);

        if (! in_array($tableName, $listTables)) {
            throw new RuntimeException(__('database.errors.focused_table_missing'));
        }

        return $listTables;
    }

    public function getSchemaForQueryByAccessMode(AgentAccessModeEnum $mode, JsonSchema $schema): array
    {
        $description = 'that changes data (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE).';

        if ($mode === AgentAccessModeEnum::ReadOnly) {
            $description = 'that only reads (SELECT, WITH, EXPLAIN, SHOW or DESCRIBE).';
        }

        $description = 'SQL query prepared with named bindings, '.$description;

        return [
            'query' => $schema->string()
                ->description($description)
                ->required(),
            'bindings' => $schema->object()
                ->description('Key-value object holding the parameters (e.g. {"id": 1, "status": "active"})')
                ->required(),
        ];
    }
}
