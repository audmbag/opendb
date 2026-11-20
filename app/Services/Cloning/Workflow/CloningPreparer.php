<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\SystemInfoService;

/**
 * Prépare un clonage : ouvre son dossier de travail et génère la configuration d'anonymisation proposée au client.
 */
class CloningPreparer
{
    public function __construct(
        private readonly CloningWorkspaceFactory $workspaces,
        private readonly CloningConnections $connections,
        private readonly SourceDatabaseStats $stats,
        private readonly SystemInfoService $systemInfoService,
    ) {}

    /**
     * Ouvre le dossier d'un nouveau clonage, sans rien lire de la source : la page en suit la progression par son
     * identifiant, et le clonage conserve toutes les données tant que l'anonymisation automatique n'est pas demandée.
     */
    public function openWorkspace(ConnectionData $source): string
    {
        $workspace = $this->workspaces->open();
        $workspace->writeEngineConfig([CloningManager::SOURCE_CONNECTION_NAME => $this->connections->engineConnection($source)]);

        return $workspace->id;
    }

    /**
     * @return array<string, mixed> Le brouillon d'anonymisation, avec l'identifiant du clonage (`cloningId`)
     */
    public function clientPayload(ConnectionData $source, ?string $cloningId = null): array
    {
        $workspace = $this->workspaces->open($cloningId);

        $workspace->writeEngineConfig([CloningManager::SOURCE_CONNECTION_NAME => $this->connections->engineConnection($source)]);

        $plan = new CloningPlan($source, $workspace, $this->connections, $this->stats, $this->systemInfoService);

        return [...$plan->clientPayload(), 'cloningId' => $workspace->id];
    }
}
