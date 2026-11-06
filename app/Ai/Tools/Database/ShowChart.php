<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\SelectQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ShowChart extends BaseTool implements Tool
{
    public const int MAX_POINTS = 500;

    public const array CHART_TYPES = ['bar', 'line', 'area', 'pie'];

    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Affiche un graphique (barres, lignes, aires ou camembert) dans l\'interface, sous forme d\'artefact, à '
            .'partir d\'une requête SELECT d\'agrégation. Ce tool ne te renvoie pas les données. '
            .'La requête doit renvoyer une colonne d\'abscisse `xColumn` (libellé, date ou catégorie) et une ou '
            .'plusieurs colonnes numériques `yColumns`, triées dans l\'ordre d\'affichage (ORDER BY), avec au plus '
            .self::MAX_POINTS.' lignes. `chartType` : line/area pour une évolution dans le temps, bar pour comparer des '
            .'catégories, pie pour une répartition (une seule colonne dans `yColumns`, 8 catégories au plus). '
            .'`stacked` empile les séries (bar et area).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $query = new SelectQuery($this->driver, (string) $request['query'], $request['bindings'] ?? []);
            $chartType = (string) $request['chartType'];
            $xColumn = (string) $request['xColumn'];
            $yColumns = array_values((array) ($request['yColumns'] ?? []));

            if (! in_array($chartType, self::CHART_TYPES, true)) {
                throw new RuntimeException('`chartType` doit valoir '.implode(', ', self::CHART_TYPES).'.');
            }

            if ($yColumns === []) {
                throw new RuntimeException('`yColumns` doit contenir au moins une colonne numérique.');
            }

            $columns = $query->columns();
            $missingColumns = $columns === [] ? [] : array_diff([$xColumn, ...$yColumns], $columns);

            if ($missingColumns !== []) {
                throw new RuntimeException('Colonnes absentes du résultat de la requête : '.implode(', ', $missingColumns).'. Colonnes disponibles : '.implode(', ', $columns).'.');
            }

            return [
                'title' => $request['title'] ?? null,
                'chartType' => $chartType,
                'xColumn' => $xColumn,
                'yColumns' => $yColumns,
                'stacked' => (bool) ($request['stacked'] ?? false),
                'totalRows' => $count = $query->count(),
                'truncated' => $count > self::MAX_POINTS,
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->selectQuerySchema($schema),
            'chartType' => $schema->string()->enum(self::CHART_TYPES)->required()->description('Type de graphique.'),
            'xColumn' => $schema->string()->required()->description('Colonne du résultat utilisée en abscisse (ou comme catégorie pour un camembert).'),
            'yColumns' => $schema->array()->items($schema->string())->min(1)->required()->description('Colonnes numériques du résultat affichées comme séries.'),
            'title' => $schema->string()->nullable()->description('Titre court du graphique.'),
            'stacked' => $schema->boolean()->default(false)->description('Empile les séries (bar et area uniquement).'),
        ];
    }
}
