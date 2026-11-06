<?php

declare(strict_types=1);

namespace App\Services\Exporter\Writers;

use App\Database\SelectQuery;
use App\Services\Exporter\Data\ExportSource;
use App\Services\Exporter\Data\TableExport;
use App\Services\Exporter\TableRowReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Un fichier CSV par table dont les données sont exportées, et `schema.sql` pour les tables dont le schéma l'est,
 * un CSV ne pouvant pas porter de schéma.
 */
class CsvExportWriter implements ExportWriter
{
    const string SCHEMA_FILE = 'schema.sql';

    public function __construct(private readonly TableRowReader $rows) {}

    public function write(ExportSource $source, array $tables, string $directory, string $baseName, DateTimeImmutable $exportedAt): array
    {
        $files = [];
        $withSchema = array_filter($tables, fn (TableExport $export): bool => $export->schema);

        if ($withSchema !== []) {
            File::put($files[] = $directory.self::SCHEMA_FILE, array_reduce(
                $withSchema,
                fn (string $sql, TableExport $export): string => $sql.$source->dialect->createTable($export->table, true)."\n",
                $source->dialect->fileHeader($source->databaseName, $exportedAt),
            ));
        }

        foreach (array_filter($tables, fn (TableExport $export): bool => $export->data) as $export) {
            $files[] = $this->writeTable($source, $export, $directory.$this->fileName($export->table->name, $files));
        }

        return $files;
    }

    /**
     * Écrit le résultat d'une requête dans un fichier CSV et renvoie le nombre de lignes écrites.
     * Sans ligne, le fichier reste vide : les colonnes d'un résultat vide ne sont pas connues.
     *
     * @throws RuntimeException
     */
    public function writeQuery(SelectQuery $query, string $path): int
    {
        $file = fopen($path, 'wb') ?: throw new RuntimeException("Impossible d'écrire le fichier d'export {$path}.");
        $rowCount = 0;

        try {
            do {
                $rows = $query->rows(TableRowReader::CHUNK_SIZE, $rowCount);

                if ($rowCount === 0 && $rows !== []) {
                    fputcsv($file, array_keys($rows[0]), escape: '');
                }

                foreach ($rows as $row) {
                    fputcsv($file, $row, escape: '');
                }

                $rowCount += count($rows);
            } while (count($rows) === TableRowReader::CHUNK_SIZE);
        } finally {
            fclose($file);
        }

        return $rowCount;
    }

    private function writeTable(ExportSource $source, TableExport $export, string $path): string
    {
        $file = fopen($path, 'wb') ?: throw new RuntimeException("Impossible d'écrire le fichier d'export {$path}.");
        $columns = $this->rows->columnNames($export->table);

        try {
            fputcsv($file, $columns, escape: '');

            foreach ($this->rows->chunks($source, $export->table) as $rows) {
                foreach ($rows as $row) {
                    fputcsv($file, array_map(fn (string $column): mixed => $row[$column] ?? null, $columns), escape: '');
                }
            }
        } finally {
            fclose($file);
        }

        return $path;
    }

    /**
     * Nom de fichier sûr et unique dans l'archive pour le CSV d'une table.
     *
     * @param  list<string>  $files
     */
    private function fileName(string $table, array $files): string
    {
        $name = trim((string) preg_replace('/[^\w.-]+/u', '_', $table), '._') ?: 'table';
        $existing = array_map(basename(...), $files);
        $fileName = $name.'.csv';

        for ($suffix = 2; in_array($fileName, $existing, true); $suffix++) {
            $fileName = "{$name}_{$suffix}.csv";
        }

        return $fileName;
    }
}
