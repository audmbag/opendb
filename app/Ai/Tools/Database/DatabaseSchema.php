<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DatabaseSchema extends BaseTool implements Tool
{
    public function __construct(
        protected DriverContract $driver
    ) {}

    public function description(): Stringable|string
    {
        return 'Décrit la structure complète de la base de données : toutes les tables, leurs colonnes '
            .'et leurs relations, en un seul appel. Ce tool est coûteux en contexte et ne doit '
            .'JAMAIS être utilisé comme point de départ d\'une exploration. '
            .'Réserve-le aux demandes explicites de vue d\'ensemble complète ou de documentation '
            .'globale de la base (ex. "montre-moi toute la structure de la base", "documente le '
            .'schéma complet"). Pour toute demande portant sur une ou plusieurs tables précises, '
            .'utilise ListTables puis FocusTable.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->toJson($this->driver->getSchema());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }
}
