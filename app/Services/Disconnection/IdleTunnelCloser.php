<?php

declare(strict_types=1);

namespace App\Services\Disconnection;

use App\Models\Project;
use App\Services\SSH\TunnelUsage;
use Exception;
use Illuminate\Support\Sleep;

/**
 * The SSH master of the project, closed when the user leaves it instead of a quarter of an hour later. Every project
 * and every user going through the same server share it: it stays open while another process still uses it.
 */
final readonly class IdleTunnelCloser
{
    /**
     * How long the runs just interrupted may take to end and let go of the master.
     */
    private const int WAIT_MILLISECONDS = 1000;

    private const int POLL_MILLISECONDS = 100;

    public function __construct(private TunnelUsage $usage) {}

    public function close(Project $project, bool $waitForInterruptedRuns = false): void
    {
        try {
            $tunnel = $project->driver_config?->sshTunnel();

            if ($tunnel === null || $this->isStillUsed($tunnel->target(), $waitForInterruptedRuns)) {
                return;
            }

            $tunnel->stopMaster();
        } catch (Exception) {
            // The master closes on its own after its delay of inactivity.
        }
    }

    private function isStillUsed(string $target, bool $wait): bool
    {
        $attempts = $wait ? intdiv(self::WAIT_MILLISECONDS, self::POLL_MILLISECONDS) : 0;

        while ($this->usage->isUsedElsewhere($target)) {
            if ($attempts-- <= 0) {
                return true;
            }

            Sleep::for(self::POLL_MILLISECONDS)->milliseconds();
        }

        return false;
    }
}
