<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

/**
 * The runs of the editor in progress, each with the connection it runs on, so that another request can interrupt it.
 */
class RunningQueries
{
    /**
     * A run longer than this is forgotten: the id of its connection could be given to another one.
     */
    private const int TTL = 3600;

    public function __construct(private Repository $cache) {}

    /**
     * Note the connection of the run, when the engine knows how to interrupt it.
     */
    public function start(DriverContract $driver, string $projectId, string $runId): void
    {
        $connectionId = $driver->connectionId();

        if ($connectionId !== null) {
            $this->cache->put($this->key($projectId, $runId), $connectionId, self::TTL);
        }
    }

    public function finish(string $projectId, string $runId): void
    {
        $this->cache->forget($this->key($projectId, $runId));
    }

    /**
     * @throws RuntimeException when the engine cannot interrupt a query, or the run is already over
     */
    public function cancel(DriverContract $driver, string $projectId, string $runId): void
    {
        $connectionId = $this->cache->pull($this->key($projectId, $runId));

        if ($connectionId === null) {
            throw new RuntimeException($driver->connectionId() === null
                ? __('tables.console.errors.cannot_cancel')
                : __('tables.console.errors.already_finished'));
        }

        if (! $driver->cancel((string) $connectionId)) {
            throw new RuntimeException(__('tables.console.cancel_failed'));
        }
    }

    private function key(string $projectId, string $runId): string
    {
        return "sql-editor:run:{$projectId}:{$runId}";
    }
}
