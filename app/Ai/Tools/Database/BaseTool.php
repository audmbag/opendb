<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
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
                ->description('Requête SQL SELECT (ou WITH … SELECT) unique, préparée avec bindings nommés, sans point-virgule multiple.')
                ->required(),
            'bindings' => $schema->object()
                ->description('Objet clé-valeur contenant les paramètres (ex: {"id": 1, "status": "active"}). Objet vide si aucun paramètre.')
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
            throw new RuntimeException("La table spécifiée dans `focusedTable.tableName` n'existe pas dans la base de données. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.");
        }

        return $listTables;
    }

    public function getSchemaForQueryByAccessMode(AgentAccessModeEnum $mode, JsonSchema $schema): array
    {
        $description = 'de modification (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE).';

        if ($mode === AgentAccessModeEnum::ReadOnly) {
            $description = 'en lecture seule (SELECT, WITH, EXPLAIN, SHOW ou DESCRIBE).';
        }

        $description = 'Requête SQL préparée avec bindings nommés '.$description;

        return [
            'query' => $schema->string()
                ->description($description)
                ->required(),
            'bindings' => $schema->object()
                ->description('Objet clé-valeur contenant les paramètres (ex: {"id": 1, "status": "active"})')
                ->required(),
        ];
    }
}
