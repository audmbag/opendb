<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\TableProfiler;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProfileTable extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Affiche dans l\'interface, sous forme d\'artefact, le profil des données d\'une table : pour chaque '
            .'colonne, le taux de valeurs NULL, le nombre de valeurs distinctes, le minimum et le maximum, la longueur '
            .'moyenne des textes et les valeurs les plus fréquentes (calculés sur un échantillon de '
            .TableProfiler::SAMPLE_SIZE.' lignes). Ce tool ne te renvoie pas ces statistiques : il sert uniquement au '
            .'rendu. Utilise-le quand l\'utilisateur veut VOIR la qualité ou la répartition des données d\'une table '
            .'(ex. "profile la table users", "quelles colonnes de orders sont mal remplies ?"). '
            .'Le paramètre `tableName` est le nom exact de la table.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $profiler = new TableProfiler($this->driver, (string) $request->string('tableName'));

            return ['table' => $profiler->table];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('Le nom exact de la table à profiler.'),
        ];
    }
}
