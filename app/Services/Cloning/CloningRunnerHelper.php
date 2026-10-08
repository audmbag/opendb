<?php

namespace App\Services\Cloning;

use App\Services\Cloning\Exception\CloningPreparationException;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\Process;

class CloningRunnerHelper
{
    public function __construct(
        protected SystemInfoService $systemInfoService,
    ) {}

    public function getDumpCommand(): string
    {
        return 'clonio cloning:dump --connection '.CloningManager::SOURCE_CONFIG_FILE.' -n --all-columns --enforce-column-types --locale='.$this->systemInfoService->locale().' --force';
    }

    public function generateSourceYamlFile(string $tmpDirectory): string
    {
        $tmpDirectory = $this->systemInfoService->temporaryDirectory($tmpDirectory);

        $process = Process::path($tmpDirectory->path(''))->run($this->getDumpCommand());

        if (! $process->successful()) {
            throw CloningPreparationException::failedToGenerateYamlSourceFile($process->errorOutput());
        }

        return $tmpDirectory->path(CloningManager::SOURCE_CONFIG_FILE.'.cloning.yaml');
    }
}
