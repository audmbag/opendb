<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use App\Services\YamlWriter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Dossier temporaire d'un clonage, ~/.{app}/tmp/{id}/ : `clonio.json`, `clonio.pii-matchers.yaml`,
 * `source.cloning.yaml`, le journal écrit par le moteur et les fichiers de CloningProgress.
 */
final readonly class CloningWorkspace
{
    const string CONFIG_FILE = 'clonio.json';

    const string PII_MATCHERS_FILE = 'clonio.pii-matchers.yaml';

    const string SOURCE_YAML_FILE = CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml';

    public function __construct(
        public string $id,
        private SystemInfoService $systemInfoService,
        private string $configTemplatePath,
        private string $piiMatchersPath,
    ) {}

    /**
     * Écrit `clonio.json` à partir du modèle, avec les connexions données au format du moteur.
     *
     * @param  array<string, array<string, mixed>>  $connections  Connexions par nom (source, target).
     */
    public function writeEngineConfig(array $connections): void
    {
        $config = json_decode(File::get($this->configTemplatePath), true);

        foreach ($connections as $name => $connection) {
            Arr::set($config, "connections.{$name}", $connection);
        }

        $this->disk()->put(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    public function piiMatchers(): array
    {
        if (! $this->disk()->fileExists(self::PII_MATCHERS_FILE)) {
            $this->disk()->put(self::PII_MATCHERS_FILE, File::get($this->piiMatchersPath));
        }

        return Yaml::parseFile($this->disk()->path(self::PII_MATCHERS_FILE));
    }

    public function hasSourceYaml(): bool
    {
        return $this->disk()->fileExists(self::SOURCE_YAML_FILE);
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceYaml(): array
    {
        return Yaml::parseFile($this->disk()->path(self::SOURCE_YAML_FILE));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function writeSourceYaml(array $config): void
    {
        $yaml = new YamlWriter(
            indent: 2,
            inline: fn (array $path): bool => in_array(array_last($path), ['foreign_keys', 'faker_arguments'], true),
        )->dump($config);

        $this->disk()->put(self::SOURCE_YAML_FILE, $yaml);
    }

    /**
     * Déplace le journal .jsonl du clonage dans ~/.{app}/logs/{id}/, puis supprime le dossier temporaire.
     *
     * @return string Nouveau chemin absolu du journal
     *
     * @throws CloningException Si le journal est introuvable ou ne peut pas être déplacé
     */
    public function archiveLog(): string
    {
        $temporaryPath = $this->systemInfoService->applicationPath('tmp', $this->id);
        $logPath = array_first(File::glob($temporaryPath.'/logs/*.jsonl'))
            ?? throw CloningException::logFileNotFound($temporaryPath);

        $destination = $this->systemInfoService->logPath($this->id.'/'.basename($logPath));

        if (! File::move($logPath, $destination)) {
            throw CloningException::failedToMoveLogFile($logPath, $destination);
        }

        File::deleteDirectory($temporaryPath);

        return $destination;
    }

    /**
     * Le suivi du clonage, qui ne crée pas le dossier : la page peut interroger un clonage déjà terminé.
     */
    public function progress(): CloningProgress
    {
        return new CloningProgress($this->systemInfoService->applicationPath('tmp', $this->id));
    }

    public function disk(): FilesystemAdapter
    {
        return $this->systemInfoService->temporaryDirectory($this->id);
    }
}
