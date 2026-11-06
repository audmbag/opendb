<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Models\Project;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExporterManager;
use App\Services\Exporter\ExportFormat;
use App\Services\Settings\UserSettings;
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
     * Dossier des exports, relatif au dossier du projet.
     */
    /**
     * Relative to the project directory, in the folder of opendb: the exports stay out of the files of the user.
     */
    const string EXPORT_DIRECTORY = Project::WORKSPACE_DIRECTORY.'/exports';

    /**
     * Valeur de `tables` dans la sortie du tool quand l'export vient d'une requête.
     */
    const string QUERY_EXPORT = 'query';

    public function __construct(
        public DriverContract $driver,
        protected ExporterManager $exporterManager,
        protected string $projectPath,
        protected UserSettings $settings = new UserSettings,
    ) {}

    public function description(): Stringable|string
    {
        return 'Exporte la base de données du projet dans un fichier compressé (zip, tar ou gzip). '
            .'Tu peux choisir d\'exporter uniquement certaines tables, avec ou sans leurs schémas '
            .'et/ou leurs données. Le format d\'exportation peut être SQL (dump) ou CSV (un fichier par table). '
            .'Ce tool te renvoie uniquement le chemin du fichier exporté, relatif au dossier du projet (dossier `'.self::EXPORT_DIRECTORY.'`), pas son contenu. '
            .'Utilise-le quand l\'utilisateur demande un export de la base de données ou de certaines tables. '
            .'Pour n\'exporter qu\'une sélection de lignes (ex. "les 40 dernières commandes"), passe `query` et `bindings` : '
            .'le résultat de la requête est exporté dans un seul fichier CSV et le tool te renvoie le nombre de lignes exportées.';
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
            throw new RuntimeException('`query` et `onlyTables` ne peuvent pas être combinés : passe l\'un ou l\'autre.');
        }

        if (($request['format'] ?? ExportFormat::Csv->value) !== ExportFormat::Csv->value) {
            throw new RuntimeException('Le résultat d\'une requête ne peut être exporté qu\'en CSV : rappelle ce tool avec le format csv, ou sans `query` pour exporter des tables en SQL.');
        }

        $export = $this->exporterManager->exportQuery(
            $this->driver,
            (string) $request['query'],
            $request['bindings'] ?? [],
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
            'path' => self::EXPORT_DIRECTORY.'/'.basename($path),
            'bytes' => filesize($path) ?: 0,
            'modified_at' => date(DATE_ATOM, (int) filemtime($path)),
        ];
    }

    protected function exportDirectory(): string
    {
        return $this->projectPath.DIRECTORY_SEPARATOR.self::EXPORT_DIRECTORY;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Requête SQL SELECT (ou WITH … SELECT) unique, préparée avec bindings nommés, dont le résultat est exporté en CSV. Ne la combine pas avec `onlyTables`. Laisse-la vide pour exporter des tables entières.')
                ->nullable(),
            'bindings' => $schema->object()
                ->description('Objet clé-valeur contenant les paramètres de `query` (ex: {"id": 1, "status": "active"}).')
                ->nullable(),
            'onlyTables' => $schema->array()
                ->description('Liste des tables à exporter. Si vide ou absent, et sans `query`, toutes les tables sont exportées avec leur schéma et leurs données.')
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
                    .'à défaut : '.$this->settings->exportFormat->value.'. En CSV, le schéma des tables est exporté dans un fichier schema.sql. Avec `query`, seul csv est possible.')
                ->enum(self::FORMATS)
                ->nullable(),
            'output' => $schema->string()
                ->description('Format de compression du fichier exporté : '.collect(self::OUTPUTS)->join(', ', ' ou ').' (none : le fichier tel quel, ou un zip s\'il y en a plusieurs). '
                    .'Respecte d\'abord la compression demandée par l\'utilisateur ; à défaut : '.$this->settings->exportCompression->value.'.')
                ->enum(self::OUTPUTS)
                ->default($this->settings->exportCompression->value)
                ->nullable(),
        ];
    }
}
