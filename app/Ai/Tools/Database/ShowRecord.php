<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\RecordInspector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShowRecord extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Affiche dans l\'interface, sous forme d\'artefact, la fiche d\'un enregistrement : toutes ses colonnes, '
            .'les enregistrements qu\'il référence (clés étrangères sortantes, navigables) et le nombre '
            .'d\'enregistrements qui le référencent (clés étrangères entrantes). Ce tool ne te renvoie pas les valeurs : '
            .'il sert uniquement au rendu. Utilise-le quand l\'utilisateur veut VOIR un enregistrement précis '
            .'(ex. "affiche la fiche du client 42", "montre-moi la commande CMD-001"). '
            .'`tableName` est le nom exact de la table ; `key` est un objet `colonne => valeur` identifiant '
            .'l\'enregistrement, de préférence sa clé primaire (ex. {"id": 42}), sinon une colonne unique (ex. {"reference": "CMD-001"}).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $record = new RecordInspector($this->driver, (string) $request->string('tableName'))
                ->find((array) ($request['key'] ?? []));

            return [
                'table' => $record['table'],
                'key' => $record['key'],
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('Le nom exact de la table de l\'enregistrement.'),
            'key' => $schema->object()
                ->required()
                ->description('Objet `colonne => valeur` identifiant l\'enregistrement, de préférence sa clé primaire (ex. {"id": 42}).'),
        ];
    }
}
