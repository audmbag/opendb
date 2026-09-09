<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTables extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Liste toutes les tables de la base de données avec un aperçu léger de chacune '
            .'(nombre de colonnes, présence d\'une clé primaire, nombre de clés étrangères), '
            .'sans détailler leurs colonnes ni leurs relations. '
            .'À utiliser en priorité, avant tout autre tool de schéma, pour repérer les tables '
            .'pertinentes par rapport à la demande de l\'utilisateur. '
            .'Une fois la ou les tables cibles identifiées, utilise FocusTable pour en obtenir le détail.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->toJson($this->driver->listTables());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }
}
