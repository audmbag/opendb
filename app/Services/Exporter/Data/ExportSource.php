<?php

declare(strict_types=1);

namespace App\Services\Exporter\Data;

use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\SqlDump\Dialects\AbstractDumpDialect;
use Illuminate\Database\Connection;

/**
 * Base exportée : sa connexion, son schéma et le dialecte SQL qui l'écrit.
 */
final readonly class ExportSource
{
    public function __construct(
        public Connection $connection,
        public DatabaseSchemaData $schema,
        public AbstractDumpDialect $dialect,
        public string $databaseName,
    ) {}
}
