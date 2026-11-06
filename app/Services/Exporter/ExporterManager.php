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
    public function __construct(
        private DatabaseExporter $exporter,
        private ExportDirectoryIgnorer $ignorer,
    ) {}

    /**
     * Garde les archives d'un dossier d'exports hors du dépôt Git du projet, avec un `.gitignore` qui ignore tout le dossier.
     */
    public function ignoreInVersionControl(string $directory): void
    {
        $this->ignorer->ignore($directory);
    }

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

    /**
     * Exporte le résultat d'une requête SELECT dans un fichier CSV archivé dans `$destinationDirectory`.
     *
     * @param  array<string|int, mixed>  $bindings
     * @param  string  $output  zip, tar, or gzip
     * @return array{path: string, rows: int} Le chemin absolu de l'archive et le nombre de lignes exportées
     *
     * @throws RuntimeException Si un paramètre est invalide, si la requête n'est pas un SELECT unique ou ne renvoie aucune ligne
     */
    public function exportQuery(DriverContract $driver, string $sql, array $bindings, string $output, string $destinationDirectory): array
    {
        return $this->exporter->exportQuery($driver, $sql, $bindings, ExportCompression::parse($output), $destinationDirectory);
    }
}
