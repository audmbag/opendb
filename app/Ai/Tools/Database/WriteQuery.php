<?php

namespace App\Ai\Tools\Database;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class WriteQuery extends QueryTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    private const array DESTRUCTIVE_KEYWORDS = ['DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'UPDATE'];

    private const array FORBIDDEN_KEYWORDS = ['GRANT', 'REVOKE', 'SHUTDOWN'];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function description(): Stringable|string
    {
        return 'Exécute une requête SQL de modification (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE) '
            .'sur la base de données courante. Les opérations destructrices nécessitent une autorisation '
            .'explicite avant exécution. Avant de supprimer une table verifie d\'abord si elle est vide, si elle est vide dis-le ouvertement à l\'utilisateur.'
            .'Les requêtes multiples (empilage de statements) ne sont pas autorisées.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) $request['query']);

        try {
            $this->assertAllowed($sql);

            $this->connection->statement($sql);
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return 'Requête exécutée avec succès.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Requête SQL de modification (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE).')
                ->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $sql = strtoupper((string) $request['query']);

        foreach (self::DESTRUCTIVE_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $sql)) {
                return Approval::required(
                    "Cette requête contient une opération destructrice ({$keyword}) sur la base de données."
                );
            }
        }

        return false;
    }

    private function assertAllowed(string $sql): void
    {
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $sql)) {
                throw new RuntimeException(
                    "Requête rejetée : {$keyword} n'est jamais autorisé, quel que soit le mode."
                );
            }
        }

        if (substr_count($sql, ';') > 1) {
            throw new RuntimeException(
                'Requête rejetée : les requêtes multiples (empilage de statements) ne sont pas autorisées.'
            );
        }
    }
}
