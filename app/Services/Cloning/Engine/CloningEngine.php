<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine;

use App\Services\Cloning\Engine\Audit\CloningAuditLog;
use App\Services\Cloning\Engine\Cloning\CloningRunOrchestrator;
use App\Services\Cloning\Engine\Cloning\CloningYamlLoader;
use App\Services\Cloning\Engine\Cloning\CloningYamlValidator;
use App\Services\Cloning\Engine\Cloning\KeyRemappingService;
use App\Services\Cloning\Engine\Config\ConfigService;
use App\Services\Cloning\Engine\Data\Cloning\CloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\CloningOptionsData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingConfigData;
use App\Services\Cloning\Engine\Data\Cloning\TableCloningConfigData;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Database\DatabaseConnectionService;
use App\Services\Cloning\Engine\Schema\SchemaInspector;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Moteur de clonage porté de `clonio cloning:run` (clonio-cli v1.1.1, licence MIT, voir LICENSE).
 *
 * Il transfère la base source décrite par un fichier `.cloning.yaml` vers une connexion cible de `clonio.json`,
 * puis écrit le journal `logs/{source}_{target}_{date}_process.jsonl` dans le dossier de travail.
 * Comme `cloning:run --allow-failure`, l'échec d'une table n'interrompt pas le clonage : il est consigné dans le journal.
 */
class CloningEngine
{
    public const string CONFIG_FILE = 'clonio.json';

    public const string LOG_DIRECTORY = 'logs';

    public function __construct(
        private readonly DatabaseConnectionService $connector,
        private readonly SchemaInspector $inspector,
        private readonly CloningRunOrchestrator $orchestrator,
    ) {}

    /**
     * @return string Le chemin du journal JSONL écrit
     *
     * @throws RuntimeException Si la configuration est invalide, si une connexion échoue ou si le remappage des clés est impossible
     */
    public function run(string $workingDirectory, string $yamlFile, string $targetName, CloningRunOptions $options = new CloningRunOptions): string
    {
        $workingDirectory = rtrim($workingDirectory, DIRECTORY_SEPARATOR);
        $existingConnections = array_keys((array) config('database.connections', []));

        try {
            return $this->transfer($workingDirectory, $yamlFile, $targetName, $options);
        } finally {
            $this->forgetTemporaryConnections($existingConnections);
        }
    }

    private function transfer(string $workingDirectory, string $yamlFile, string $targetName, CloningRunOptions $options): string
    {
        $config = $this->applyOptions($this->loadConfig($workingDirectory.DIRECTORY_SEPARATOR.$yamlFile), $options);
        $skipTables = array_values(array_unique([...$options->skipTables, ...$config->skipTables]));

        $configService = new ConfigService($workingDirectory.DIRECTORY_SEPARATOR.self::CONFIG_FILE);
        $source = $configService->getConnection($config->connectionName)
            ?? throw new RuntimeException(sprintf("Source connection '%s' not found in %s.", $config->connectionName, self::CONFIG_FILE));
        $target = $configService->getConnection($targetName)
            ?? throw new RuntimeException(sprintf("Target connection '%s' not found in %s.", $targetName, self::CONFIG_FILE));

        if ($config->connectionName === $targetName) {
            throw new RuntimeException('Source and target cannot be the same connection.');
        }

        $this->checkConnection($source, sprintf("Cannot connect to source '%s'", $config->connectionName));
        $this->checkConnection($target, sprintf("Cannot connect to target '%s'", $targetName));

        $log = new CloningAuditLog;
        $startedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $log->info('run_started', ['source' => $config->connectionName, 'target' => $targetName]);

        try {
            $sourceSchema = $this->inspector->inspect($source);
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf('Failed to inspect source schema: %s', $throwable->getMessage()), 0, $throwable);
        }

        $keyRemapping = null;

        if ($config->keyRemapping instanceof KeyRemappingConfigData && $config->keyRemapping->isActive()) {
            $keyRemapping = new KeyRemappingService($this->connector);
            $keyRemapping->generateMappings(
                $config->keyRemapping,
                $source,
                $sourceSchema,
                array_map(static fn (TableCloningConfigData $table): string => $table->tableName, $config->tables),
            );
        }

        $result = $this->orchestrator->run(
            log: $log,
            config: $config,
            source: $source,
            target: $target,
            sourceSchema: $sourceSchema,
            skipSchema: false,
            skipTables: $skipTables,
            onlyTables: [],
            onProgress: static function (): void {},
            keyRemapping: $keyRemapping,
            breakOnFailure: $options->breakOnFailure,
        );

        $log->info('run_finished', ['success' => $result->success, 'rows' => $result->totalRows]);

        if ($keyRemapping instanceof KeyRemappingService) {
            $keyRemapping->cleanup();
            $log->info('key_mapping_cleanup_completed');
        }

        return $this->writeLog($workingDirectory, $log, $config->connectionName, $targetName, $startedAt);
    }

    private function loadConfig(string $path): CloningConfigData
    {
        try {
            $config = new CloningYamlLoader()->load($path);
            $rawData = Yaml::parseFile($path);
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf('Failed to parse or validate YAML: %s', $throwable->getMessage()), 0, $throwable);
        }

        if (is_array($rawData)) {
            $errors = new CloningYamlValidator()->validate($rawData);

            if ($errors !== []) {
                throw new RuntimeException(implode("\n", $errors));
            }
        }

        return $config;
    }

    private function applyOptions(CloningConfigData $config, CloningRunOptions $options): CloningConfigData
    {
        $current = $config->options;

        return new CloningConfigData(
            version: $config->version,
            connectionName: $config->connectionName,
            options: new CloningOptionsData(
                chunkSize: $current->chunkSize,
                enforceColumnTypes: $options->enforceColumnTypes ?? $current->enforceColumnTypes,
                dropUnknownTables: $options->dropUnknownTables ?? $current->dropUnknownTables,
                dropExtraColumns: $options->dropExtraColumns ?? $current->dropExtraColumns,
                disableForeignKeyChecks: $options->disableForeignKeyChecks ?? $current->disableForeignKeyChecks,
                fakerLocale: $current->fakerLocale,
            ),
            tables: $config->tables,
            keyRemapping: $config->keyRemapping,
            skipTables: $config->skipTables,
        );
    }

    private function checkConnection(ConnectionData $connection, string $errorPrefix): void
    {
        try {
            DB::purge($this->connector->open($connection));
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf('%s: %s', $errorPrefix, $throwable->getMessage()), 0, $throwable);
        }
    }

    private function writeLog(string $workingDirectory, CloningAuditLog $log, string $source, string $target, DateTimeImmutable $startedAt): string
    {
        $directory = $workingDirectory.DIRECTORY_SEPARATOR.self::LOG_DIRECTORY;
        $path = $directory.DIRECTORY_SEPARATOR.sprintf('%s_%s_%s_process.jsonl', $source, $target, $startedAt->format('Y-m-d\TH-i-s\Z'));

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create the log directory %s.', $directory));
        }

        if (file_put_contents($path, $log->toJsonl()) === false) {
            throw new RuntimeException(sprintf('Cannot write the cloning log %s.', $path));
        }

        return $path;
    }

    /**
     * Retire les connexions `clonio_*` que le clonage a ajoutées à `config('database.connections')`.
     *
     * @param  list<string|int>  $existingConnections
     */
    private function forgetTemporaryConnections(array $existingConnections): void
    {
        $connections = (array) config('database.connections', []);

        foreach (array_diff(array_keys($connections), $existingConnections) as $name) {
            DB::purge((string) $name);
            unset($connections[$name]);
        }

        config(['database.connections' => $connections]);
    }
}
