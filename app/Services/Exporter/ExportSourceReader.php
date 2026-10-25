<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Database\Contracts\DriverContract;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use App\Services\Cloning\Engine\Schema\SchemaInspector;
use App\Services\Cloning\Engine\SqlDump\Dialects\AbstractDumpDialect;
use App\Services\Cloning\Engine\SqlDump\DumpDialectFactory;
use App\Services\Exporter\Data\ExportSource;
use RuntimeException;

/**
 * Lit le schéma de la base d'un driver d'opendb, sur sa propre connexion (tunnel SSH compris).
 */
class ExportSourceReader
{
    public function __construct(
        private readonly SchemaInspector $inspector,
        private readonly DumpDialectFactory $dialects,
    ) {}

    /**
     * @throws RuntimeException Si le type de base n'est pas exportable
     */
    public function read(DriverContract $driver): ExportSource
    {
        $connection = $driver->getConnection();
        $type = DatabaseConnectionType::tryFrom($connection->getDriverName());

        if ($type === null || $type === DatabaseConnectionType::Dump) {
            throw new RuntimeException("L'export n'est pas disponible pour les bases {$connection->getDriverName()}.");
        }

        $dialect = $this->dialects->make($type);

        if (! $dialect instanceof AbstractDumpDialect) {
            throw new RuntimeException("Aucun dialecte SQL d'export pour les bases {$type->label()}.");
        }

        $source = new ConnectionData(
            name: $connection->getName(),
            type: $type,
            host: null,
            port: null,
            database: $connection->getDatabaseName(),
            schema: null,
            username: null,
            password: '',
            isProduction: false,
        );

        return new ExportSource(
            connection: $connection,
            schema: $this->inspector->inspectOpenConnection($connection->getName(), $source),
            dialect: $dialect,
            databaseName: $this->fileSafeName($type === DatabaseConnectionType::Sqlite ? pathinfo((string) $source->database, PATHINFO_FILENAME) : (string) $source->database),
        );
    }

    private function fileSafeName(string $name): string
    {
        return trim((string) preg_replace('/[^\w.-]+/u', '_', $name), '._') ?: 'export';
    }
}
