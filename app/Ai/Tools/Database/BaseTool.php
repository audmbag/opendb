<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use Illuminate\Database\QueryException;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

class BaseTool
{
    protected function formatQueryError(QueryException $e): string
    {
        $driverMessage = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $cleaned = preg_replace('/SQLSTATE\[\w+\]:\s*/', '', explode(' (Connection:', $driverMessage)[0]);

        return "La requête a échoué : {$cleaned}. Vérifie les noms des colonnes/tables avant de réessayer.";
    }

    protected function toJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
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
