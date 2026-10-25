<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningResult;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\Data\CloningRequest;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;

/**
 * Exécute un clonage préparé : configuration finale, base cible, moteur de clonage puis archivage du journal.
 */
class CloningExecutor
{
    public function __construct(
        private readonly CloningWorkspaceFactory $workspaces,
        private readonly CloningConnections $connections,
        private readonly SourceDatabaseStats $stats,
        private readonly SystemInfoService $systemInfoService,
        private readonly CloningRunnerHelper $runner,
    ) {}

    /**
     * @throws CloningException Si le remappage dépasse la mémoire, si le clonage ne démarre pas ou si son journal est introuvable
     */
    public function execute(CloningRequest $request): CloningResult
    {
        $workspace = $this->workspaces->open($request->cloningId);
        $workspace->writeEngineConfig([
            CloningManager::SOURCE_CONNECTION_NAME => $this->connections->engineConnection($request->source),
            CloningManager::TARGET_CONNECTION_NAME => $this->connections->engineConnection($request->target),
        ]);

        new CloningPlan($request->source, $workspace, $this->connections, $this->stats, $this->systemInfoService)
            ->applyClientDraft($request->clientDraft);

        $target = $this->connections->driver($request->target);

        if ($request->createTargetDatabase) {
            $target->createDatabaseIfNotExists();
        }

        $this->runner->cloneNow($workspace->id, $request->options, $request->skipTables);

        $target->forgetSchema();

        return CloningResult::for($workspace->archiveLog());
    }
}
