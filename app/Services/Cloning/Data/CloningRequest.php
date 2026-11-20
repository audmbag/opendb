<?php

declare(strict_types=1);

namespace App\Services\Cloning\Data;

use App\Models\DriverConfig;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Workflow\CloningConnections;

/**
 * Clonage validé par le client : source, cible, brouillon d'anonymisation et options.
 */
final readonly class CloningRequest
{
    /**
     * @param  array<string, mixed>  $clientDraft  Tables du brouillon d'anonymisation, par nom.
     * @param  bool  $automaticAnonymisation  Faux si le client n'a pas demandé l'anonymisation : tout est conservé.
     * @param  array<string, bool>  $options  Options de CloningRunnerHelper::DEFAULT_CLONING_OPTIONS à surcharger.
     * @param  list<string>  $skipTables
     */
    public function __construct(
        public string $cloningId,
        public ConnectionData $source,
        public ConnectionData $target,
        public array $clientDraft = [],
        public array $options = [],
        public array $skipTables = [],
        public bool $createTargetDatabase = false,
        public bool $automaticAnonymisation = true,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Données validées par SaveWithCloningRequest.
     */
    public static function fromClientPayload(array $payload): self
    {
        $target = $payload['cloningDriverConfig'];

        return new self(
            cloningId: $payload['anonymisation']['cloningId'],
            source: CloningConnections::fromDriverConfig(DriverConfig::findOrFail($target['from_id'])),
            target: CloningConnections::fromArray(CloningManager::TARGET_CONNECTION_NAME, $target),
            clientDraft: $payload['anonymisation']['tables'],
            options: $payload['options'],
            skipTables: $payload['cloningDisabled'],
            createTargetDatabase: (bool) $target['create_database_if_not_exists'],
            automaticAnonymisation: (bool) $payload['anonymisation']['automatic'],
        );
    }
}
