<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExporterManager;
use App\Services\Exporter\ExportFormat;
use App\Services\Settings\UserSettings;
use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ExportDatabase extends BaseTool implements Tool
{
    const array FORMATS = [ExportFormat::Sql->value, ExportFormat::Csv->value];

    /**
     * `none` keeps the file as it is when the export makes only one, and zips the files otherwise.
     */
    const array OUTPUTS = [ExportCompression::Zip->value, ExportCompression::Tar->value, ExportCompression::Gzip->value, ExportCompression::None->value];

    /**
     * Valeur de `tables` dans la sortie du tool quand l'export vient d'une requête.
     */
    const string QUERY_EXPORT = 'query';

    public function __construct(
        public DriverContract $driver,
        protected ExporterManager $exporterManager,
        protected ProjectWorkspace $workspace,
        protected UserSettings $settings = new UserSettings,
    ) {}

    public function description(): Stringable|string
    {
        return 'Exports the database of the project to a compressed file (zip, tar or gzip). '
            .'You can export only some tables, with or without their schemas '
            .'and/or their data. The export format can be SQL (dump) or CSV (one file per table). '
            .'This tool only returns the path of the exported file to you, relative to the project folder (folder `'.$this->workspace->relativePath(ProjectWorkspace::EXPORTS).'`), not its content. '
            .'Use it when the user asks for an export of the database or of some tables. '
            .'To export only a selection of rows (e.g. "the last 40 orders"), pass `query` and `bindings`: '
            .'the result of the query is exported to a single CSV file and the tool returns the number of exported rows to you.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $output = $request['output'] ?? $this->settings->exportCompression->value;

            if (filled($request['query'] ?? null)) {
                return $this->exportQuery($request, $output);
            }

            $format = $request['format'] ?? $this->settings->exportFormat->value;
            $onlyTables = collect($request['onlyTables'] ?? [])
                ->filter(fn (mixed $table): bool => is_array($table) && filled($table['table'] ?? null))
                ->mapWithKeys(fn (array $table): array => [(string) $table['table'] => [
                    'schema' => (bool) ($table['schema'] ?? true),
                    'data' => (bool) ($table['data'] ?? true),
                ]])
                ->all();

            $path = $this->exporterManager->export($this->driver, $onlyTables, $format, $output, $this->exportDirectory());

            return [
                ...$this->describeArchive($path),
                'format' => $format,
                'output' => $output,
                'tables' => $onlyTables === [] ? 'all' : array_keys($onlyTables),
            ];
        });
    }

    /**
     * Exporte le résultat d'une requête : un fichier CSV, seul format capable de porter des lignes qui ne viennent pas d'une table précise.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException Si la requête est combinée à `onlyTables` ou au format SQL
     */
    protected function exportQuery(Request $request, string $output): array
    {
        if (filled($request['onlyTables'] ?? null)) {
            throw new RuntimeException('`query` and `onlyTables` cannot be combined: pass one or the other.');
        }

        if (($request['format'] ?? ExportFormat::Csv->value) !== ExportFormat::Csv->value) {
            throw new RuntimeException('The result of a query can only be exported as CSV: call this tool again with the csv format, or without `query` to export tables as SQL.');
        }

        $export = $this->exporterManager->exportQuery(
            $this->driver,
            (string) $request['query'],
            QueryBindings::from($request['bindings'] ?? null),
            $output,
            $this->exportDirectory(),
        );

        return [
            ...$this->describeArchive($export['path']),
            'format' => ExportFormat::Csv->value,
            'output' => $output,
            'tables' => self::QUERY_EXPORT,
            'rows' => $export['rows'],
        ];
    }

    /**
     * What the chat interface displays about an archive.
     *
     * @return array{path: string, bytes: int, modified_at: string}
     */
    protected function describeArchive(string $path): array
    {
        // Written once the archive exists: a failed export leaves no directory behind.
        $this->exporterManager->ignoreInVersionControl(dirname($path));

        return [
            'path' => $this->workspace->relativePath(ProjectWorkspace::EXPORTS.'/'.basename($path)),
            'bytes' => filesize($path) ?: 0,
            'modified_at' => date(DATE_ATOM, (int) filemtime($path)),
        ];
    }

    protected function exportDirectory(): string
    {
        return $this->workspace->path(ProjectWorkspace::EXPORTS);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('A single SQL SELECT (or WITH … SELECT) query, prepared with named bindings, whose result is exported as CSV. Do not combine it with `onlyTables`. Leave it empty to export whole tables.')
                ->nullable(),
            'bindings' => $schema->object()
                ->description('Key-value object holding the parameters of `query` (e.g. {"id": 1, "status": "active"}).')
                ->nullable(),
            'onlyTables' => $schema->array()
                ->description('List of the tables to export. When empty or missing, and without `query`, every table is exported with its schema and its data.')
                ->items($schema->object(fn ($schema) => [
                    'table' => $schema->string()
                        ->description('Exact name of the table, as returned by `ListTables`.')
                        ->required(),
                    'schema' => $schema->boolean()
                        ->description('Include the schema of the table in the export.')
                        ->required(),
                    'data' => $schema->boolean()
                        ->description('Include the data of the table in the export.')
                        ->required(),
                ]))
                ->nullable(),
            'format' => $schema->string()
                ->description('Export format: '.collect(self::FORMATS)->join(', ', ' or ').'. '
                    .'First follow the format asked by the user or given in the project memory; '
                    .'otherwise: '.$this->settings->exportFormat->value.'. In CSV, the schema of the tables is exported to a schema.sql file. With `query`, only csv is possible.')
                ->enum(self::FORMATS)
                ->nullable(),
            'output' => $schema->string()
                ->description('Compression format of the exported file: '.collect(self::OUTPUTS)->join(', ', ' or ').' (none: the file as it is, or a zip when there are several). '
                    .'First follow the compression asked by the user; otherwise: '.$this->settings->exportCompression->value.'.')
                ->enum(self::OUTPUTS)
                ->default($this->settings->exportCompression->value)
                ->nullable(),
        ];
    }
}
