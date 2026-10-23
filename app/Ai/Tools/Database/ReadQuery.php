<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\Contracts\DriverContract;
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
        return 'Exécute une requête SQL en lecture seule (SELECT, WITH, EXPLAIN, SHOW ou DESCRIBE) '
            .'sur la base de données courante et retourne les lignes obtenues '
            ."si une requête SELECT est demandée, ajoute toujours une LIMITe de {$this->maxRows} et précise très "
            .'bien à l\'utilisateur que la requête est tronquée si le nombre de lignes retournées est égal à cette limite.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) $request['query']);
        $bindings = $request['bindings'] ?? [];

        try {
            ReadOnlyQueryGuard::assertReadOnly($sql);

            $rows = $this->driver->executeReadOnly($sql, $bindings);

            $rows = array_slice($rows, 0, $this->maxRows);
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        if (empty($rows)) {
            return '[]';
        }

        $json = $this->toJson($rows);

        return count($rows) === $this->maxRows
            ? $json."\n\n(Résultat tronqué à {$this->maxRows} lignes.)"
            : $json;
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->getSchemaForQueryByAccessMode(AgentAccessModeEnum::ReadOnly, $schema);
    }
}
