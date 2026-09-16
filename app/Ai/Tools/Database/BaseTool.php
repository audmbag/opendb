<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\Contracts\DriverContract;
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
     * @param string $tableName
     * @param DriverContract $driver
     * @return array
     *
     * @throws RuntimeException if the table does not exist in the database.
     */
    protected function validateTable(string $tableName, DriverContract $driver): array
    {
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
