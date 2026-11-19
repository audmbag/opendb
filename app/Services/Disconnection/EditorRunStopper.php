<?php

declare(strict_types=1);

namespace App\Services\Disconnection;

use App\Models\Project;
use App\Models\User;
use App\Services\SqlEditor\SqlEditorManager;
use Exception;

/**
 * The runs of the SQL editor still in progress when the user leaves the project, interrupted as its Stop button does.
 */
final readonly class EditorRunStopper
{
    public function __construct(private SqlEditorManager $editor) {}

    /**
     * @param  list<string>  $runIds
     * @return int the number of runs interrupted
     */
    public function stop(Project $project, User $user, array $runIds): int
    {
        if ($runIds === []) {
            return 0;
        }

        try {
            $driver = $project->driver_config->driverFor($user);
        } catch (Exception) {
            // The database cannot be reached any more: its runs end on their own.
            return 0;
        }

        try {
            return array_reduce($runIds, function (int $interrupted, string $runId) use ($driver, $project): int {
                try {
                    $this->editor->cancel($driver, $project, $runId);

                    return $interrupted + 1;
                } catch (Exception) {
                    // Already over, or an engine that cannot interrupt a query: there is nothing more to do.
                    return $interrupted;
                }
            }, 0);
        } finally {
            $driver->disconnect();
        }
    }
}
