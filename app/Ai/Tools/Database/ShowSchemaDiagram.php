<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ShowSchemaDiagram extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Affiche un diagramme visuel du schéma de la base de données dans l\'interface, sous forme '
            .'d\'artefact. Ce tool ne renvoie AUCUNE donnée à analyser en profondeur : il sert '
            .'uniquement à déclencher un rendu graphique pour l\'utilisateur, jamais à comprendre ou '
            .'décrire la structure de la base toi-même (utilise ListTables, FocusTable ou DatabaseSchema '
            .'pour ça). Utilise ce tool uniquement quand l\'utilisateur demande explicitement à VOIR un '
            .'diagramme, un schéma visuel ou une représentation graphique (ex. "affiche-moi le diagramme '
            .'de la base", "montre-moi le schéma visuel de la table users"). '
            .'Le paramètre `focusedTable` est un objet nullable : laisse-le à null pour afficher le '
            .'diagramme complet de la base, ou renseigne-le pour centrer le diagramme sur une seule table. '
            .'`focusedTable.tableName` (obligatoire si focusedTable est renseigné) est le nom exact de la '
            .'table cible. `focusedTable.withRelatedTables` (booléen) indique si les tables liées par clé '
            .'étrangère (entrante ou sortante) doivent aussi apparaître dans le diagramme.';
    }

    public function handle(Request $request): Stringable|string
    {
        $focusedTable = $request['focusedTable'] ?? [];

        try {
            $validatedFocusedTable = $this->validateAndGetTables($focusedTable);
        } catch (RuntimeException $e) {
            return $this->toJson(['success' => false, 'error' => $e->getMessage()]);
        }

        return $this->toJson(['success' => true, ...$validatedFocusedTable]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'focusedTable' => $schema->object(fn ($schema) => [
                'tableName' => $schema->string()->required()->description('Le nom de la table cible.'),
                'withRelatedTables' => $schema->boolean()->required()->description('Indique si les tables liées par clé étrangère dans les deux sens doivent aussi apparaître dans le diagramme.'),
            ])
                ->nullable()
                ->description('Un objet nullable qui, s\'il est renseigné, indique que le diagramme doit être centré sur une seule table. Laisse-le à null pour afficher le diagramme complet de la base.'),
        ];
    }

    private function validateAndGetTables(array $focusedTable): array
    {
        $listTables = $this->driver->listTables(false);

        if (empty($focusedTable)) {
            return [
                'focusedTable' => null,
                'withRelatedTables' => false,
                'displayedTables' => $listTables,
                'displayedTablesCount' => count($listTables),
            ];
        }

        if (! isset($focusedTable['tableName'])) {
            throw new RuntimeException('Si `focusedTable` est renseigné, il doit contenir la clé `tableName` avec le nom exact de la table cible.');
        }

        if (! isset($focusedTable['withRelatedTables'])) {
            throw new RuntimeException('Si `focusedTable` est renseigné avec un `tableName`, il doit aussi contenir la clé `withRelatedTables` avec une valeur booléenne.');
        }

        $table = $focusedTable['tableName'];
        $withRelatedTables = (bool) $focusedTable['withRelatedTables'];
        $displayedTables = [];

        if (! in_array($table, $listTables)) {
            throw new RuntimeException("La table spécifiée dans `focusedTable.tableName` n'existe pas dans la base de données. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.");
        }

        if ($withRelatedTables) {
            $tables = $this->driver->focusTable($table);

            $displayedTables = array_map(fn (array $table): string => $table['name'], $tables);
        }

        return [
            'focusedTable' => $table,
            'withRelatedTables' => $withRelatedTables,
            'displayedTables' => $displayedTables,
            'displayedTablesCount' => count($displayedTables),
        ];
    }
}
