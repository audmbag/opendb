<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Database\Contracts\DriverContract;
use App\Database\SelectQuery;
use App\Enums\PrivilegeEnum;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Exporter\Writers\CsvExportWriter;
use App\Services\SystemInfoService;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Exporte une base : lecture du schéma, sélection des tables, écriture des fichiers puis archivage.
 */
class DatabaseExporter
{
    public function __construct(
        private readonly ExportSourceReader $sourceReader,
        private readonly ExportTableSelector $tableSelector,
        private readonly ExportArchiver $archiver,
        private readonly SystemInfoService $systemInfoService,
        private readonly CsvExportWriter $csvWriter,
    ) {}

    /**
     * @param  array<string, array{schema?: bool, data?: bool}>  $onlyTables  Toutes les tables, schéma et données, si vide.
     * @return string Le chemin absolu de l'archive
     *
     * @throws RuntimeException
     */
    public function export(DriverContract $driver, array $onlyTables, ExportFormat $format, ExportCompression $compression, string $destinationDirectory): string
    {
        $source = $this->sourceReader->read($driver);

        foreach (array_keys($onlyTables) as $table) {
            $driver->assertPrivilege(PrivilegeEnum::Export, (string) $table);
        }

        $tables = $this->tableSelector->select($source->schema, $onlyTables, [...$driver->hiddenTables(), ...$this->notExportable($driver, $source->schema)]);
        $exportedAt = new DateTimeImmutable;
        $baseName = $source->databaseName.'_'.$exportedAt->format('Ymd_His');
        $workingDirectory = $this->systemInfoService->temporaryDirectory('export_'.Str::random(12))->path('');

        try {
            $files = $format->writer()->write($source, $tables, $workingDirectory, $baseName, $exportedAt);

            File::ensureDirectoryExists($destinationDirectory);

            return $this->archiver->archive($files, rtrim($destinationDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName, $compression);
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    /**
     * Exporte le résultat d'une requête SELECT dans un fichier CSV archivé.
     *
     * @param  array<string|int, mixed>  $bindings
     * @return array{path: string, rows: int} Le chemin absolu de l'archive et le nombre de lignes exportées
     *
     * @throws RuntimeException Si la requête n'est pas un SELECT unique ou ne renvoie aucune ligne
     */
    public function exportQuery(DriverContract $driver, string $sql, array $bindings, ExportCompression $compression, string $destinationDirectory): array
    {
        $query = new SelectQuery($driver, $sql, $bindings);
        $baseName = $this->sourceReader->databaseName($driver).'_'.new DateTimeImmutable()->format('Ymd_His');
        $workingDirectory = $this->systemInfoService->temporaryDirectory('export_'.Str::random(12))->path('');

        try {
            $file = $workingDirectory.$baseName.'.csv';
            $rows = $this->csvWriter->writeQuery($query, $file);

            if ($rows === 0) {
                throw new RuntimeException(__('exports.errors.empty_query'));
            }

            File::ensureDirectoryExists($destinationDirectory);

            return [
                'path' => $this->archiver->archive([$file], rtrim($destinationDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName, $compression),
                'rows' => $rows,
            ];
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    /**
     * The tables the user may read but not export, left out of an export of every table.
     *
     * @return list<string>
     */
    private function notExportable(DriverContract $driver, DatabaseSchemaData $schema): array
    {
        $privileges = $driver->privileges();

        if ($privileges === null || $privileges->allowsOnProject(PrivilegeEnum::Export)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (TableSchemaData $table): string => $table->name, $schema->tables),
            fn (string $table): bool => ! $privileges->allows(PrivilegeEnum::Export, $table),
        ));
    }
}
