<?php

namespace App\Repository;

use App\Models\QueryHistoryEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QueryHistoryRepository
{
    /**
     * The runs kept for each user in each project: the oldest ones are forgotten as new ones come.
     */
    public const int KEPT_ENTRIES = 500;

    /**
     * @param  array{file: ?string, sql: string, succeeded: bool, statements: int, rows: ?int, duration: int, error: ?string}  $entry
     */
    public function record(string $projectId, int $userId, array $entry): QueryHistoryEntry
    {
        $recorded = QueryHistoryEntry::create(['project_id' => $projectId, 'user_id' => $userId, ...$entry]);

        // The identifiers follow the order of the runs: the first one beyond the limit marks the entries to forget.
        $oldestForgotten = $this->of($projectId, $userId)
            ->orderByDesc('id')
            ->skip(self::KEPT_ENTRIES)
            ->value('id');

        if ($oldestForgotten !== null) {
            $this->of($projectId, $userId)->where('id', '<=', $oldestForgotten)->delete();
        }

        return $recorded;
    }

    /**
     * The latest runs of the user in the project, the most recent first, whose script contains the search when one is given.
     *
     * @return Collection<int, QueryHistoryEntry>
     */
    public function search(string $projectId, int $userId, ?string $search = null, int $limit = 100): Collection
    {
        return $this->of($projectId, $userId)
            // The wildcards typed by the user are searched as such.
            ->when($search !== null && $search !== '', fn ($query) => $query->whereRaw("sql LIKE ? ESCAPE '\\'", ['%'.addcslashes($search, '%_\\').'%']))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function clear(string $projectId, int $userId): void
    {
        $this->of($projectId, $userId)->delete();
    }

    /**
     * @return Builder<QueryHistoryEntry>
     */
    private function of(string $projectId, int $userId): Builder
    {
        return QueryHistoryEntry::query()->where('project_id', $projectId)->where('user_id', $userId);
    }
}
