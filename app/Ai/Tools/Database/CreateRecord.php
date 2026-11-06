<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableRecordForm;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateRecord extends BaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        private readonly DriverContract $driver,
    ) {}

    /**
     * The cache key of the values the user submitted in the form of a tool call.
     */
    public static function submissionCacheKey(string $toolCallId): string
    {
        return "form-submission:{$toolCallId}";
    }

    public function description(): Stringable|string
    {
        return 'Prépare la création d\'un enregistrement dans une table. '
            .'N\'écris JAMAIS toi-même une requête INSERT pour créer un enregistrement : '
            .'utilise systématiquement cet outil avec le nom de la table cible. '
            .'Un formulaire interactif, généré à partir de la structure de la table, sera présenté à l\'utilisateur ; '
            .'n\'invente pas de valeurs pour les champs que l\'utilisateur n\'a pas encore fournis. '
            .'Une fois l\'enregistrement créé, donne systématiquement à l\'utilisateur ses détails : '
            .'la table, l\'identifiant généré s\'il y en a un, et chaque colonne renseignée avec sa valeur, '
            .'tels que renvoyés par cet outil (présente-les sous forme de liste ou de tableau). '
            .'Si l\'utilisateur a annulé le formulaire ou si la création a échoué, indique-le clairement avec la raison.';
    }

    public function handle(Request $request): Stringable|string
    {
        $table = (string) $request->string('table');

        $submission = Cache::pull(static::submissionCacheKey((string) $request->toolCallId()));

        if (! is_array($submission) || $submission['table'] !== $table) {
            return "Aucune donnée de formulaire n'a été soumise pour cet appel : l'enregistrement n'a pas été créé.";
        }

        try {
            $record = new TableRecordForm($this->driver, $table)->insert($submission['values']);
        } catch (HiddenTableException|TableNotFoundException $e) {
            return $e->getMessage();
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        }

        return "Enregistrement créé dans `{$table}` : ".$this->toJson($record);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()
                ->description('Nom exact de la table cible, tel qu\'identifié via ListTables.')
                ->required(),
            'known_values' => $schema->object(fn ($schema) => [])
                ->description('Valeurs déjà mentionnées par l\'utilisateur dans la conversation, à préremplir dans le formulaire, indexées par nom de colonne.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Saisie utilisateur requise via formulaire.');
    }
}
