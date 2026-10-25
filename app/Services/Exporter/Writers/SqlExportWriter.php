<?php

declare(strict_types=1);

namespace App\Services\Exporter\Writers;

use App\Services\Exporter\Data\ExportSource;
use App\Services\Exporter\Data\TableExport;
use App\Services\Exporter\TableRowReader;
use DateTimeImmutable;
use RuntimeException;

/**
 * Un seul fichier `.sql` : les `CREATE TABLE IF NOT EXISTS` des tables, puis leurs `INSERT`, clés étrangères désactivées.
 */
class SqlExportWriter implements ExportWriter
{
    public function __construct(private readonly TableRowReader $rows) {}

    public function write(ExportSource $source, array $tables, string $directory, string $baseName, DateTimeImmutable $exportedAt): array
    {
        $path = $directory.$baseName.'.sql';
        $file = fopen($path, 'wb') ?: throw new RuntimeException("Impossible d'écrire le fichier d'export {$path}.");
        $dialect = $source->dialect;

        try {
            fwrite($file, $dialect->fileHeader($source->databaseName, $exportedAt).$dialect->preamble(true));

            foreach (array_filter($tables, fn (TableExport $export): bool => $export->schema) as $export) {
                fwrite($file, $dialect->createTable($export->table, true)."\n");
            }

            foreach (array_filter($tables, fn (TableExport $export): bool => $export->data) as $export) {
                foreach ($this->rows->chunks($source, $export->table) as $rows) {
                    fwrite($file, $dialect->insertBatch($export->table, $this->rows->columnNames($export->table), $rows));
                }
            }

            fwrite($file, $dialect->postamble(true, array_map(fn (TableExport $export) => $export->table, $tables)));
        } finally {
            fclose($file);
        }

        return [$path];
    }
}
