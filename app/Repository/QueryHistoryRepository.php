<?php

namespace App\Repository;

use App\Models\QueryHistoryEntry;
use Illuminate\Support\Collection;

class QueryHistoryRepository
{
    /**
     * The runs kept for each project: the oldest ones are forgotten as new ones come.
     */
    public const int KEPT_ENTRIES = 500;

    /**
     * @param  array{file: ?string, sql: string, succeeded: bool, statements: int, rows: ?int, duration: int, error: ?string}  $entry
     */
    public function record(string $projectId, array $entry): QueryHistoryEntry
    {
        $recorded = QueryHistoryEntry::create(['project_id' => $projectId, ...$entry]);

        // The identifiers follow the order of the runs: the first one beyond the limit marks the entries to forget.
        $oldestForgotten = QueryHistoryEntry::query()
            ->where('project_id', $projectId)
            ->orderByDesc('id')
            ->skip(self::KEPT_ENTRIES)
            ->value('id');

        if ($oldestForgotten !== null) {
            QueryHistoryEntry::query()->where('project_id', $projectId)->where('id', '<=', $oldestForgotten)->delete();
        }

        return $recorded;
    }

    /**
     * The latest runs of the project, the most recent first, whose script contains the search when one is given.
     *
     * @return Collection<int, QueryHistoryEntry>
     */
    public function search(string $projectId, ?string $search = null, int $limit = 100): Collection
    {
        return QueryHistoryEntry::query()
            ->where('project_id', $projectId)
            // The wildcards typed by the user are searched as such.
            ->when($search !== null && $search !== '', fn ($query) => $query->whereRaw("sql LIKE ? ESCAPE '\\'", ['%'.addcslashes($search, '%_\\').'%']))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function clear(string $projectId): void
    {
        QueryHistoryEntry::query()->where('project_id', $projectId)->delete();
    }
}
