<?php

declare(strict_types=1);

namespace App\Services\Cloning;

use App\Models\DriverConfig;
use App\Services\Cloning\Data\CloningRequest;
use App\Services\Cloning\Engine\Exceptions\CloningCancelledException;
use App\Services\Cloning\Exception\CloningException;
use App\Services\Cloning\Workflow\CloningConnections;
use App\Services\Cloning\Workflow\CloningExecutor;
use App\Services\Cloning\Workflow\CloningPreparer;
use App\Services\Cloning\Workflow\CloningWorkspaceFactory;

/**
 * Passerelle entre l'application et le clonage de bases de données.
 */
final readonly class CloningManager
{
    const string SOURCE_CONNECTION_NAME = 'source';

    const string TARGET_CONNECTION_NAME = 'target';

    public function __construct(
        private CloningPreparer $preparer,
        private CloningExecutor $executor,
        private CloningWorkspaceFactory $workspaces,
    ) {}

    /**
     * Brouillon d'anonymisation proposé au client pour cloner la base de `$config`, avec l'identifiant du clonage.
     *
     * @return array<string, mixed>
     */
    public function clientPayload(DriverConfig $config): array
    {
        return $this->preparer->clientPayload(CloningConnections::fromDriverConfig($config));
    }

    /**
     * Clone la base source vers la cible selon le payload validé par le client.
     *
     * @param  array<string, mixed>  $clientPayload  Données validées par SaveWithCloningRequest.
     *
     * @throws CloningException
     * @throws CloningCancelledException
     */
    public function cloneClientPayload(array $clientPayload): CloningResult
    {
        return $this->executor->execute(CloningRequest::fromClientPayload($clientPayload));
    }

    /**
     * Avancement du clonage `$cloningId`, null s'il n'est pas en cours.
     *
     * @return array<string, mixed>|null
     */
    public function progress(string $cloningId): ?array
    {
        return $this->workspaces->open($cloningId)->progress()->poll();
    }

    /**
     * Arrête le clonage `$cloningId` après le lot de lignes en cours.
     */
    public function cancel(string $cloningId): void
    {
        $this->workspaces->open($cloningId)->progress()->cancel();
    }
}
