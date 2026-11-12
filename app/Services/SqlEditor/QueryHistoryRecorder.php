<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Repository\QueryHistoryRepository;
use App\Services\Workspace\ProjectWorkspace;

/**
 * Keeps a run of the editor in the history of the user in the project, summed up from what the console returned.
 */
class QueryHistoryRecorder
{
    public function __construct(private QueryHistoryRepository $history) {}

    /**
     * @param  array{results: list<array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}>, error: ?array{index: int, statement: string, message: string}}  $run
     */
    public function record(ProjectWorkspace $workspace, ?string $file, string $sql, array $run): void
    {
        $last = array_last($run['results']);

        $this->history->record($workspace->project->id, $workspace->user->id, [
            'file' => $file,
            'sql' => trim($sql),
            'succeeded' => $run['error'] === null,
            'statements' => count($run['results']) + ($run['error'] === null ? 0 : 1),
            // What the last statement gave: its rows, or the rows it changed.
            'rows' => $last === null ? null : ($last['columns'] === null ? $last['affected'] : count($last['rows'])),
            'duration' => array_sum(array_column($run['results'], 'duration')),
            'error' => $run['error']['message'] ?? null,
        ]);
    }
}
