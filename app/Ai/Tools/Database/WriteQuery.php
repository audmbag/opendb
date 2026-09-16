<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class WriteQuery extends BaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    private const array DESTRUCTIVE_KEYWORDS = ['DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'UPDATE'];

    private const array FORBIDDEN_KEYWORDS = ['GRANT', 'REVOKE', 'SHUTDOWN'];

    public function __construct(
        private readonly DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Exécute une requête SQL de modification (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE) '
            .'sur la base de données courante. '
            .'Si vous souhaitez supprimer une table, vérifiez d\'abord si elle est vide. Si elle est vide, indiquez-le clairement à l\'utilisateur.'
            .'Les requêtes multiples (empilage de statements) ne sont pas autorisées.'
            .'Note importante: pour les opérations insertion des informations qui sont généralement hashées comme le mot de passe,'
            .'vérifie d\'abord si l\'utilisateur a fourni un mot de passe en clair, si c\'est le cas, hash-le avant de l\'insérer dans la base des données. '
            .'Pour les opérations de suppression d\'enregistrements(DELETE), utilise toujours la clause WHERE sur la clé primaire s\'il y en a une, sinon sur un identifiant unique, et jamais sur un champ qui peut contenir des doublons.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) $request['query']);
        $bindings = $request['bindings'];

        try {
            $this->assertAllowed($sql);

            $this->driver->statement($sql, $bindings);
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return 'Requête exécutée avec succès.';
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->getSchemaForQueryByAccessMode(AgentAccessModeEnum::ReadWrite, $schema);
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $raw_sql = (string) $request['query'];
        $sql = strtoupper($raw_sql);

        foreach (self::DESTRUCTIVE_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $sql)) {
                return Approval::required(
                    "`$raw_sql` : Cette requête contient une opération destructrice ({$keyword}) sur la base de données. Voulez-vous autoriser l'outil à continuer ?"
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
