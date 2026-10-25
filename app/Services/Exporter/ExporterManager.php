<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Database\Contracts\DriverContract;
use RuntimeException;

/**
 * Passerelle entre l'application et l'export de bases de données.
 */
final readonly class ExporterManager
{
    public function __construct(private DatabaseExporter $exporter) {}

    /**
     * Exporte les tables de la base du driver dans une archive écrite dans `$destinationDirectory`.
     *
     * @param  array<string, array{schema?: bool, data?: bool}>  $onlyTables  table names to export, with options for schema and data (e.g. ['users' => ['schema' => true, 'data' => false]]); every table, with its schema and data, when empty
     * @param  string  $format  sql or csv
     * @param  string  $output  zip, tar, or gzip
     * @return string Le chemin absolu de l'archive
     *
     * @throws RuntimeException Si un paramètre est invalide, si une table n'existe pas ou si l'archive ne peut pas être écrite
     */
    public function export(DriverContract $driver, array $onlyTables, string $format, string $output, string $destinationDirectory): string
    {
        return $this->exporter->export($driver, $onlyTables, ExportFormat::parse($format), ExportCompression::parse($output), $destinationDirectory);
    }
}
