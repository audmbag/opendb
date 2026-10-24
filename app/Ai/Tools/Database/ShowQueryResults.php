<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\SelectQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShowQueryResults extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Affiche le résultat d\'une requête SELECT dans un tableau interactif de l\'interface (tri, pagination, '
            .'export CSV), sous forme d\'artefact. Ce tool ne te renvoie AUCUNE ligne : seulement les colonnes et le '
            .'nombre total de lignes. Utilise-le quand l\'utilisateur veut VOIR, parcourir ou exporter des données '
            .'(ex. "montre-moi les commandes de la semaine", "liste les clients inactifs"), surtout quand le résultat '
            .'peut dépasser quelques dizaines de lignes. N\'ajoute pas de LIMIT : la pagination est faite par l\'interface. '
            .'Si tu dois analyser les valeurs toi-même pour répondre, utilise ReadQuery à la place. '
            .'Le paramètre `title` est un titre court affiché au-dessus du tableau.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $query = new SelectQuery($this->driver, (string) $request['query'], $request['bindings'] ?? []);

            return [
                'title' => $request['title'] ?? null,
                'columns' => $query->columns(),
                'totalRows' => $query->count(),
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->selectQuerySchema($schema),
            'title' => $schema->string()
                ->nullable()
                ->description('Titre court affiché au-dessus du tableau (ex. "Commandes de la semaine").'),
        ];
    }
}
