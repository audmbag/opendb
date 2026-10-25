<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\SystemInfoService;
use Illuminate\Support\Str;

class CloningWorkspaceFactory
{
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
