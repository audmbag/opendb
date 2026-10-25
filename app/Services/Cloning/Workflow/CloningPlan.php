<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\Cloning\CloningAnonymizationConfig;
use App\Services\Cloning\CloningSourceConfig;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;

/**
 * Configuration d'anonymisation de la base source d'un clonage : `source.cloning.yaml` initial, généré depuis le
 * schéma et les statistiques de la source, puis complété par le brouillon validé du client.
 */
final class CloningPlan
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $schema = null;

    private ?CloningSourceConfig $sourceConfig = null;

    private ?CloningAnonymizationConfig $anonymizationConfig = null;

    public function __construct(
        private readonly ConnectionData $source,
        private readonly CloningWorkspace $workspace,
        private readonly CloningConnections $connections,
        private readonly SourceDatabaseStats $stats,
        private readonly SystemInfoService $systemInfoService,
    ) {}

    /**
     * Tables et colonnes proposées au client, à partir de `source.cloning.yaml` (généré au premier appel).
     *
     * @return array<string, mixed>
     */
    public function clientPayload(): array
    {
        return $this->anonymizationConfig()->toClientPayload();
    }

    /**
     * Réécrit `source.cloning.yaml` avec le brouillon validé du client.
     *
     * @param  array<string, mixed>  $clientDraft
     *
     * @throws CloningException Si le remappage choisi dépasse la mémoire, qui échouerait sinon en cours de route
     */
    public function applyClientDraft(array $clientDraft): void
    {
        $config = $this->anonymizationConfig()->parser($clientDraft);

        $remapped = array_keys(array_filter(
            $config['tables'],
            fn (array $table): bool => array_any($table['columns'] ?? [], fn (array $column): bool => ($column['strategy'] ?? null) === 'remapping'),
        ));

        $overflow = $remapped === [] ? [] : $this->sourceConfig()->remappingOverflow(array_map('strval', $remapped));

        if ($overflow !== []) {
            throw CloningException::remappingExceedsMemory($overflow);
        }

        $this->workspace->writeSourceYaml($config);
    }

    private function anonymizationConfig(): CloningAnonymizationConfig
    {
        if (! $this->workspace->hasSourceYaml()) {
            $this->workspace->writeSourceYaml($this->sourceConfig()->toRaw());
        }

        return $this->anonymizationConfig ??= new CloningAnonymizationConfig(
            raw: $this->workspace->sourceYaml(),
            dbSchema: $this->schema(),
            piiMatchers: $this->workspace->piiMatchers(),
            keptPrimaryKeys: $this->sourceConfig()->keptPrimaryKeys(),
        );
    }

    private function sourceConfig(): CloningSourceConfig
    {
        if ($this->sourceConfig !== null) {
            return $this->sourceConfig;
        }

        $driver = $this->connections->driver($this->source);
        $primaryKeyRanges = $this->stats->primaryKeyRanges($driver, $this->schema());

        return $this->sourceConfig = new CloningSourceConfig(
            $this->schema(),
            $this->workspace->piiMatchers(),
            $this->systemInfoService->locale(),
            $primaryKeyRanges,
            $this->stats->largestRowBytes($driver, $primaryKeyRanges),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schema(): array
    {
        return $this->schema ??= $this->connections->driver($this->source)->getSchema() ?? [];
    }
}
