<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\SystemInfoService;
use Illuminate\Support\Str;

class CloningWorkspaceFactory
{
    /**
     * Forme de l'identifiant d'un clonage, sans délimiteurs : la date de sa préparation suivie de 8 caractères.
     */
    const string ID_PATTERN = '\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[A-Za-z0-9]{8}';

    public function __construct(
        private readonly SystemInfoService $systemInfoService,
        private readonly string $configTemplatePath,
        private readonly string $piiMatchersPath,
    ) {}

    /**
     * Ouvre le dossier du clonage `$id`, ou celui d'un nouveau clonage.
     */
    public function open(?string $id = null): CloningWorkspace
    {
        return new CloningWorkspace(
            id: $id ?? now()->format('Y-m-d_H-i-s').'_'.Str::random(8),
            systemInfoService: $this->systemInfoService,
            configTemplatePath: $this->configTemplatePath,
            piiMatchersPath: $this->piiMatchersPath,
        );
    }
}
