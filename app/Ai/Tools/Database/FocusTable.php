<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FocusTable extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Décrit en détail une table spécifique : ses colonnes (nom, type, nullable, valeur par '
            .'défaut), sa clé primaire, ses index et ses clés étrangères. '
            .'À utiliser après ListTables, une fois le nom exact de la table identifié. '
            .'Le paramètre `tableName` est le nom de la table cible. '
            .'Le paramètre `withRelatedTables` (booléen) charge en plus la structure des tables liées '
            .'par clé étrangère (dans les deux sens). Mets-le à true si la demande implique une jointure, '
            .'un filtre sur une table liée, ou une compréhension du contexte relationnel de la table ; '
            .'laisse-le à false si seule la structure de cette table isolée est nécessaire.';
    }

    public function handle(Request $request): Stringable|string
    {
        $tableName = $request->string('tableName');
        $withRelatedTables = $request->boolean('withRelatedTables');

        $schema = $this->driver->focusTable($tableName, $withRelatedTables);

        return $this->toJson($schema);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('Le nom de la table cible.'),
            'withRelatedTables' => $schema->boolean()
                ->default(false)
                ->description('Indique si les tables liées à la table cible doivent être aussi chargées.'),
        ];
    }
}
