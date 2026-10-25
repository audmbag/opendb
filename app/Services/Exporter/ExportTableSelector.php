<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Services\Cloning\Engine\Cloning\DependencyResolver;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Exporter\Data\TableExport;
use RuntimeException;

/**
 * Tables demandées pour un export, vérifiées et triées dans l'ordre de leurs clés étrangères.
 */
class ExportTableSelector
{
    public function __construct(private readonly DependencyResolver $dependencyResolver) {}

    /**
     * @param  array<string, array{schema?: bool, data?: bool}>  $onlyTables  Toutes les tables, schéma et données, si vide.
     * @return list<TableExport>
     *
     * @throws RuntimeException Si une table n'existe pas ou si rien n'est à exporter
     */
    public function select(DatabaseSchemaData $schema, array $onlyTables): array
    {
        $onlyTables = $onlyTables !== []
            ? $onlyTables
            : array_fill_keys(array_map(fn (TableSchemaData $table): string => $table->name, $schema->tables), []);

        $unknownTables = array_filter(array_keys($onlyTables), fn (string|int $table): bool => ! $schema->hasTable((string) $table));

        if ($unknownTables !== []) {
            throw new RuntimeException('Ces tables n\'existent pas dans la base de données : '.implode(', ', $unknownTables).'. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.');
        }

        $tables = $this->dependencyResolver->sort($schema, array_map('strval', array_keys($onlyTables)))
            |> (fn (array $names): array => array_map(fn (string $name): TableExport => new TableExport(
                table: $schema->getTable($name),
                schema: (bool) ($onlyTables[$name]['schema'] ?? true),
                data: (bool) ($onlyTables[$name]['data'] ?? true),
            ), $names));

        if (! array_any($tables, fn (TableExport $table): bool => $table->schema || $table->data)) {
            throw new RuntimeException('Rien à exporter : chaque table demandée exclut à la fois son schéma et ses données.');
        }

        return $tables;
    }
}
