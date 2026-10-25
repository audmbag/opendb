<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Database\Contracts\DriverContract;
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
        $tables = $this->tableSelector->select($source->schema, $onlyTables);
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
}
