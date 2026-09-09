<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
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

    public function description(): Stringable|string
    {
        return 'Prépare la création d\'un enregistrement dans une table. '
            .'N\'écris JAMAIS toi-même une requête INSERT pour créer un enregistrement : '
            .'utilise systématiquement cet outil avec le nom de la table cible. '
            .'Un formulaire interactif sera présenté à l\'utilisateur ; n\'invente pas de valeurs '
            .'pour les champs que l\'utilisateur n\'a pas encore fournis.';
    }


    public function handle(Request $request): Stringable|string
    {
        $table = $request['table'];

        $submitted = Cache::pull("form-submission:{$request->toolCallId()}");

        if (! $submitted) {
            return "Aucune donnée de formulaire trouvée pour cet appel. L'utilisateur n'a peut-être pas encore soumis.";
        }

        try {
            $id = $this->driver->getConnection()->table($table)->insertGetId($submitted);
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        }

        return "Enregistrement créé dans `{$table}` (id={$id}).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()
                ->description('Nom exact de la table cible, tel qu\'identifié via DescribeSchema.')
                ->required(),
            'known_values' => $schema->object(fn ($schema) => [])
                ->description('Valeurs déjà mentionnées par l\'utilisateur dans la conversation, à préremplir dans le formulaire.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Saisie utilisateur requise via formulaire.');
    }
}
