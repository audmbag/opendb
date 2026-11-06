<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Ai\Agents\Tables\QueryReviewerAgent;
use App\Database\Contracts\DriverContract;
use App\Services\QueryPlan\Exception\QueryPlanException;
use App\Services\QueryPlan\QueryPlanManager;
use Laravel\Ai\Enums\Lab;
use RuntimeException;

/**
 * A statement of the editor read by the agent: fixed after an error, explained, or made faster with its execution plan.
 */
class QueryReviewer
{
    public function __construct(private QueryPlanManager $plans) {}

    /**
     * @param  'fix'|'explain'|'optimize'  $mode
     * @return array{explanation: string, sql: ?string}
     *
     * @throws RuntimeException when the agent explains nothing
     */
    public function review(DriverContract $driver, string $projectId, string $mode, string $sql, ?string $error, Lab|string $provider, string $model): array
    {
        $response = new QueryReviewerAgent($driver, $mode, $sql, $error, $mode === 'optimize' ? $this->plan($driver, $projectId, $sql) : null)
            ->prompt(match ($mode) {
                'fix' => 'Corrige cette instruction.',
                'explain' => 'Explique cette instruction.',
                'optimize' => 'Optimise cette instruction.',
            }, provider: $provider, model: $model);

        $explanation = trim((string) ($response['explanation'] ?? ''));
        $rewritten = trim((string) ($response['sql'] ?? ''));

        if ($explanation === '') {
            throw new RuntimeException("L'assistant n'a pas répondu. Réessayez.");
        }

        return ['explanation' => $explanation, 'sql' => $rewritten === '' ? null : $rewritten];
    }

    /**
     * The summary of the plan the agent optimizes from, or null when the statement has none, like an UPDATE.
     *
     * @return ?array<string, mixed>
     */
    private function plan(DriverContract $driver, string $projectId, string $sql): ?array
    {
        try {
            return $this->plans->summarize($this->plans->explain($driver, $projectId, null, $sql, []));
        } catch (QueryPlanException) {
            return null;
        }
    }
}
