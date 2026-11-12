<?php

namespace App\Services\Usage;

/**
 * The usage of the models by every agent of the application, shown in the settings.
 */
class UsageManager
{
    public function __construct(protected UsageStatistics $statistics) {}

    /**
     * @param  ?int  $days  the period, ending today, or null for the whole history
     * @return array<string, mixed>
     */
    public function statistics(?int $days): array
    {
        return $this->statistics->summarize($days);
    }
}
