<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ReadQuery extends BaseTool implements Tool
{
    private const array ALLOWED_PREFIXES = ['SELECT', 'WITH', 'EXPLAIN', 'SHOW', 'DESCRIBE'];

    private const array FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
        'CREATE', 'GRANT', 'REVOKE', 'REPLACE', 'MERGE', 'CALL',
    ];

    private const array ALLOWED_SQLITE_PRAGMAS = [
        'table_info', 'table_xinfo', 'foreign_key_list',
        'index_list', 'index_info', 'index_xinfo',
        'database_list', 'collation_list', 'pragma_list',
        'compile_options', 'schema_version', 'user_version',
        'application_id', 'encoding',
    ];

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
        $bindings = $request['bindings'];

        $this->assertReadOnly($sql);

        try {
            $this->assertReadOnly($sql);

            $rows = $this->driver->executeReadOnly($sql);

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

    private function assertReadOnly(string $sql): void
    {
        $normalized = Str::of($sql)->trim()->upper();

        if ($normalized->startsWith('PRAGMA')) {
            $this->assertSafePragma($sql);

            return;
        }

        $startsWithAllowed = collect(self::ALLOWED_PREFIXES)
            ->contains(fn (string $prefix) => $normalized->startsWith($prefix));

        if (! $startsWithAllowed) {
            throw new RuntimeException(
                'Requête rejetée : seules les instructions SELECT, WITH, EXPLAIN, SHOW et DESCRIBE '
                .'sont autorisées par ce tool.'
            );
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $sql)) {
                throw new RuntimeException(
                    "Requête rejetée : le mot-clé {$keyword} n'est pas autorisé dans ce tool en lecture seule."
                );
            }
        }

        if (substr_count($sql, ';') > 1) {
            throw new RuntimeException(
                'Requête rejetée : les requêtes multiples (empilage de statements) ne sont pas autorisées.'
            );
        }
    }

    private function assertSafePragma(string $sql): void
    {
        if (Str::contains($sql, '=')) {
            throw new RuntimeException(
                "Requête rejetée : les PRAGMA de configuration (avec '=') ne sont pas autorisés en lecture seule."
            );
        }

        preg_match('/PRAGMA\s+(\w+)/i', $sql, $matches);
        $pragmaName = strtolower($matches[1] ?? '');

        if (! in_array($pragmaName, self::ALLOWED_SQLITE_PRAGMAS, true)) {
            throw new RuntimeException(
                "Requête rejetée : le pragma '{$pragmaName}' n'est pas dans la liste blanche autorisée."
            );
        }
    }
}
