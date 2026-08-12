<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

readonly class DescribeSchema implements Tool
{
    public function __construct(
        protected DriverContract $driver
    ) {}

    public function description(): Stringable|string
    {
        return 'Décrit la structure de la base de données : tables, colonnes et relations. '
            .'Passe l\'argument `table` pour limiter le résultat à une seule table nommée — '
            .'c\'est le cas le plus courant et le plus rapide, à privilégier dès qu\'une table '
            .'précise est mentionnée dans la demande. N\'omets `table` que pour une vue '
            .'d\'ensemble explicitement multi-tables ou globale.';
    }

    public function handle(Request $request): Stringable|string
    {
        $table = $request['table'] ?? null;

        $data = array_filter([
            'tables' => $this->driver->describeTables($table),
            'database' => $table ? $this->driver->describeDatabase() : null,
            'relations' => $table ? $this->driver->describeRelations() : null,
        ], fn ($item) => $item !== null);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()
                ->description(
                    "Nom d'une table pour limiter le résultat à celle-ci uniquement. "
                    .'À fournir systématiquement quand la demande porte sur une seule table nommée '
                    .'(ex: "quelles sont les colonnes de la table users ?"). '
                    .'Omettre uniquement pour une vue d\'ensemble multi-tables '
                    .'(ex: "montre-moi tout le schéma", "quelles tables existent").'
                )
                ->nullable(),
        ];
    }
}
