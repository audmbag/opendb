<?php

namespace App\Services\Cloning;

use App\Database\Drivers\BaseDriver;
use App\Models\DriverConfig;
use App\Services\Cloning\Data\CloningConnection;
use App\Services\Cloning\Exception\CloningPreparationException;
use App\Services\SystemInfoService;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class CloningManager
{
    public ?CloningConnection $fromConfig = null;

    public ?CloningConnection $toConfig = null;

    public ?string $tmpDirectory = null;

    const string PII_MATCHERS_FILE = 'clonio.pii-matchers.yaml';

    const string CLONING_CONFIG_FILE = 'clonio.json';

    const string SOURCE_CONFIG_FILE = 'source';

    public function __construct(
        protected string $cloning_config_path,
        protected string $pii_matchers_path,
        protected SystemInfoService $systemInfoService,
        protected CloningRunnerHelper $cloningRunnerHelper
    ) {}

    protected function PIIMarchersRaw(): array
    {
        return Yaml::parseFile($this->pii_matchers_path);
    }

    protected function createCloningFileConfig(): string
    {
        $cloningConfigBase = json_decode(file_get_contents($this->cloning_config_path), true);

        Arr::set($cloningConfigBase, 'connections.source', $this->fromConfig->toArray());

        if ($this->toConfig) {
            Arr::set($cloningConfigBase, 'connections.target', $this->toConfig->toArray());
        }

        Arr::set($cloningConfigBase, 'audit.channels.local.path', $this->systemInfoService->logDirectory()->path(''));

        $this->tmpDirectory ??= now()->format('Y-m-d_H-i-s').'_'.Str::random(8);

        $this->systemInfoService->temporaryDirectory($this->tmpDirectory)->put(
            self::CLONING_CONFIG_FILE,
            json_encode($cloningConfigBase, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $this->systemInfoService->temporaryDirectory($this->tmpDirectory)->path(self::CLONING_CONFIG_FILE);
    }

    protected function setSource(CloningConnection $config): self
    {
        $this->fromConfig = $config;

        return $this;
    }

    public function from(DriverConfig|CloningConnection $config): self
    {
        $instance = app(self::class);

        if ($config instanceof DriverConfig) {
            $config = CloningConnection::fromDriverConfig($config);
        }

        return $instance->setSource($config);
    }

    public function to(CloningConnection $connection): self
    {
        $this->toConfig = $connection;

        return $this;
    }

    public function cloneNow(): bool
    {
        if (! $this->toConfig) {
            throw CloningPreparationException::targetConnectionNotSet();
        }

        try {
            $this->createCloningFileConfig();

            $this->copyPIIMatchersFile();

            $this->cloningRunnerHelper->generateSourceYamlFile($this->tmpDirectory);

        } catch (Exception $e) {
            throw new RuntimeException('Failed to run cloning: '.$e->getMessage());
        }

        return true;
    }

    private function copyPIIMatchersFile(): void
    {
        $this->systemInfoService->temporaryDirectory($this->tmpDirectory)->put(
            self::PII_MATCHERS_FILE,
            file_get_contents($this->pii_matchers_path)
        );
    }

    public function toClientPayload(): array
    {
        if (! $this->fromConfig) {
            throw CloningPreparationException::sourceConnectionNotSet();
        }

        $config = new CloningAnonymizationConfig(
            raw: $this->getFromRawConfig(),
            dbSchema: BaseDriver::for([
                ...$this->fromConfig->toArray(),
                'driver_id' => $this->fromConfig->type,
                'password' => $this->fromConfig->decryptedPassword,
            ])->getSchema() ?? [],
            piiMatchers: $this->PIIMarchersRaw()
        );

        return [...$config->toClientPayload(), 'cloningId' => $this->tmpDirectory];
    }

    /**
     * Get the raw configuration for the source connection after generated the .yaml file by running Clonio.
     */
    private function getFromRawConfig(): array
    {
        $this->createCloningFileConfig();

        $this->copyPIIMatchersFile();

        $yamlFile = $this->cloningRunnerHelper->generateSourceYamlFile($this->tmpDirectory);

        return Yaml::parseFile($yamlFile);
    }
}
