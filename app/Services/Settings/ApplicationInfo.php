<?php

namespace App\Services\Settings;

use App\Services\SystemInfoService;

/**
 * What the user may need to know about the running application, such as where it keeps its data.
 */
class ApplicationInfo
{
    public function __construct(protected SystemInfoService $system) {}

    /**
     * @return array{name: string, environment: string, phpVersion: string, laravelVersion: string, operatingSystem: string, dataDirectory: string, logDirectory: string}
     */
    public function describe(): array
    {
        return [
            'name' => (string) config('app.name'),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'operatingSystem' => $this->system->os()->getLabel(),
            'dataDirectory' => $this->system->applicationPath(),
            'logDirectory' => $this->system->applicationPath('logs'),
        ];
    }
}
