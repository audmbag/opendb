<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\AdminStatementGuard;
use App\Database\Contracts\DriverContract;
use App\Database\HiddenTableGuard;
use App\Database\StackedStatementGuard;
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

    /**
     * @var array<string, string> The destructive keyword, with what a statement starting with it does to the data.
     */
    private const array DESTRUCTIVE_OPERATIONS = [
        'DELETE' => "Cette requête supprime définitivement des lignes de la table : elles ne pourront être récupérées qu'à partir d'une sauvegarde.",
        'DROP' => "Cette requête supprime définitivement un objet de la base (table, vue, index…). S'il s'agit d'une table, toutes ses données sont perdues.",
        'TRUNCATE' => 'Cette requête vide entièrement la table : toutes ses lignes sont supprimées, sans annulation possible.',
        'ALTER' => 'Cette requête modifie la structure de la table. Selon le changement, des colonnes ou des données existantes peuvent être perdues.',
        'UPDATE' => "Cette requête remplace des valeurs existantes : les anciennes valeurs sont écrasées et ne pourront être récupérées qu'à partir d'une sauvegarde.",
    ];

    public function __construct(
        private readonly DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Exécute une requête SQL de modification (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE) '
            .'sur la base de données courante. '
            .'Si vous souhaitez supprimer une table, vérifiez d\'abord si elle est vide. Si elle est vide, indiquez-le clairement à l\'utilisateur.'
            .'Les requêtes multiples (empilage de statements) ne sont pas autorisées.'
            .'Les opérations réservées à un administrateur (comptes, rôles, droits, bases, serveur) sont toujours refusées. '
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

        if (AdminStatementGuard::isAdministrative($raw_sql)
            || StackedStatementGuard::hasSeveralStatements($raw_sql)
            || HiddenTableGuard::rejects($raw_sql, $this->driver->hiddenTables())) {
            return false;
        }

        $sql = strtoupper(StackedStatementGuard::withoutTextValues($raw_sql));

        foreach (array_keys(self::DESTRUCTIVE_OPERATIONS) as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $sql)) {
                return Approval::required($this->approvalReason($sql, $keyword));
            }
        }

        return false;
    }

    /**
     * Explain the operation from the keyword starting the statement, since the matched keyword may only be a part of it (ON DELETE CASCADE, ALTER … DROP COLUMN).
     * Any other query gets a warning that does not claim what it does.
     */
    private function approvalReason(string $sql, string $matchedKeyword): string
    {
        preg_match('/^\s*(\w+)/', $sql, $matches);

        $explanation = self::DESTRUCTIVE_OPERATIONS[$matches[1] ?? '']
            ?? "Cette requête contient le mot-clé {$matchedKeyword}, qui peut supprimer ou écraser des données selon son utilisation. Vérifiez-la attentivement.";

        return $explanation.' Voulez-vous autoriser son exécution ?';
    }

    private function assertAllowed(string $sql): void
    {
        AdminStatementGuard::assertNotAdministrative($sql);
        StackedStatementGuard::assertSingleStatement($sql);
    }
}
