<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use App\Services\QueryConsole\QueryConsoleManager;
use App\Services\QueryConsole\RunOptions;
use App\Services\QueryConsole\TransactionMode;
use App\Services\Workspace\ProjectWorkspace;
use RuntimeException;

/**
 * A run of the editor: the console of the Tables tab runs the statements, with the values of their parameters, in
 * the chosen transaction, read-only when the project asks for it. The run can be interrupted while it lasts, each
 * result says the table whose cells can be changed in it, and the run is kept in the history.
 */
class EditorRunner
{
    public function __construct(
        private QueryConsoleManager $console,
        private QueryHistoryRecorder $recorder,
        private RunningQueries $running,
        private ResultSourceDetector $sources,
    ) {}

    /**
     * @param  array<string, ?string>  $bindings
     * @param  ?string  $runId  chosen by the editor, to interrupt the run from another request
     * @return array{results: list<array<string, mixed>>, error: ?array{index: int, statement: string, message: string}, transaction: ?string, readOnly: bool}
     *
     * @throws RuntimeException when there is nothing to run, or the database could not undo what the mode would undo
     */
    public function run(DriverContract $driver, ProjectWorkspace $workspace, string $sql, ?string $file, array $bindings, TransactionMode $mode, ?string $runId): array
    {
        $project = $workspace->project;
        $readOnly = (bool) $project->driver_config->read_only;
        $options = new RunOptions(
            bindings: $bindings,
            mode: $mode,
            beforeRun: $runId === null ? null : fn (DriverContract $driver) => $this->running->start($driver, $project->id, $runId),
            readOnly: $readOnly,
        );

        try {
            $run = $this->console->run($driver, $sql, QueryConsoleManager::ROW_LIMIT, $options);
        } finally {
            if ($runId !== null) {
                $this->running->finish($project->id, $runId);
            }
        }

        $this->recorder->record($workspace, $file, $sql, $run);

        return [
            ...$run,
            'results' => array_map(fn (array $result): array => [
                ...$result,
                // Read-only, a result is never changed.
                'source' => $readOnly || $result['columns'] === null ? null : $this->sources->detect($driver, $result['statement'], $result['columns']),
            ], $run['results']),
            'readOnly' => $readOnly,
        ];
    }
}
