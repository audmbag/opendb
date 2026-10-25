<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExporterManager;
use App\Services\Exporter\ExportFormat;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExportDatabase extends BaseTool implements Tool
{
    const array FORMATS = [ExportFormat::Sql->value, ExportFormat::Csv->value];

    const string DEFAULT_FORMAT = ExportFormat::Sql->value;

    const array OUTPUTS = [ExportCompression::Zip->value, ExportCompression::Tar->value, ExportCompression::Gzip->value];

    const string DEFAULT_OUTPUT = ExportCompression::Zip->value;

    /**
     * Dossier des exports, relatif au dossier du projet.
     */
    const string EXPORT_DIRECTORY = 'exports';

    public function __construct(
        public DriverContract $driver,
        protected ExporterManager $exporterManager,
        protected string $projectPath,
    ) {}

    public function description(): Stringable|string
    {
        return 'Exporte la base de données du projet dans un fichier compressé (zip, tar ou gzip). '
            .'Tu peux choisir d\'exporter uniquement certaines tables, avec ou sans leurs schémas '
            .'et/ou leurs données. Le format d\'exportation peut être SQL (dump) ou CSV (un fichier par table). '
            .'Ce tool te renvoie uniquement le chemin du fichier exporté, relatif au dossier du projet (dossier `'.self::EXPORT_DIRECTORY.'`), pas son contenu. '
            .'Utilise-le quand l\'utilisateur demande un export de la base de données ou de certaines tables. ';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $format = $request['format'] ?? self::DEFAULT_FORMAT;
            $output = $request['output'] ?? self::DEFAULT_OUTPUT;
            $onlyTables = collect($request['onlyTables'] ?? [])
                ->filter(fn (mixed $table): bool => is_array($table) && filled($table['table'] ?? null))
                ->mapWithKeys(fn (array $table): array => [(string) $table['table'] => [
                    'schema' => (bool) ($table['schema'] ?? true),
                    'data' => (bool) ($table['data'] ?? true),
                ]])
                ->all();

            $path = $this->exporterManager->export(
                $this->driver,
                $onlyTables,
                $format,
                $output,
                $this->projectPath.DIRECTORY_SEPARATOR.self::EXPORT_DIRECTORY,
            );

            return [
                'path' => self::EXPORT_DIRECTORY.'/'.basename($path),
                'format' => $format,
                'output' => $output,
                'tables' => $onlyTables === [] ? 'all' : array_keys($onlyTables),
                'bytes' => filesize($path) ?: 0,
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'onlyTables' => $schema->array()
                ->description('Liste des tables à exporter. Si vide ou absent, toutes les tables sont exportées avec leur schéma et leurs données.')
                ->items($schema->object(fn ($schema) => [
                    'table' => $schema->string()
                        ->description('Nom exact de la table, tel que renvoyé par `ListTables`.')
                        ->required(),
                    'schema' => $schema->boolean()
                        ->description('Inclure le schéma de la table dans l\'exportation.')
                        ->required(),
                    'data' => $schema->boolean()
                        ->description('Inclure les données de la table dans l\'exportation.')
                        ->required(),
                ]))
                ->nullable(),
            'format' => $schema->string()
                ->description('Format d\'exportation : '.collect(self::FORMATS)->join(', ', ' ou ').'. '
                    .'Respecte d\'abord le format demandé par l\'utilisateur ou indiqué dans la mémoire du projet ; '
                    .'à défaut : '.self::DEFAULT_FORMAT.'. En CSV, le schéma des tables est exporté dans un fichier schema.sql.')
                ->enum(self::FORMATS)
                ->nullable(),
            'output' => $schema->string()
                ->description('Format de compression du fichier exporté : '.collect(self::OUTPUTS)->join(', ', ' ou ').'. Par défaut : '.self::DEFAULT_OUTPUT.'.')
                ->enum(self::OUTPUTS)
                ->default(self::DEFAULT_OUTPUT)
                ->nullable(),
        ];
    }
}
