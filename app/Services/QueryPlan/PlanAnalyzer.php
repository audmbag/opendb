<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\HiddenTableException;
use App\Database\ReadOnlyQueryGuard;
use App\Models\QueryPlan;
use App\Repository\QueryPlanRepository;
use App\Services\QueryPlan\Exception\QueryPlanException;
use App\Services\QueryPlan\Parsers\QueryPlanParserResolver;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Calcule, normalise et stocke le plan d'une requête au moment où l'agent le demande, comparé au plan précédent
 * de la même requête dans la conversation.
 */
final readonly class PlanAnalyzer
{
    public function __construct(
        private QueryPlanParserResolver $parsers,
        private TableIndexReader $indexReader,
        private PlanAlertDetector $alertDetector,
        private PlanComparator $comparator,
        private PlanExplainer $explainer,
        private QueryPlanRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $bindings
     *
     * @throws QueryPlanException
     */
    public function analyze(DriverContract $driver, string $projectId, ?string $conversationId, string $sql, array $bindings): QueryPlan
    {
        try {
            $sql = ReadOnlyQueryGuard::assertExplainable($sql);
        } catch (RuntimeException $e) {
            throw new QueryPlanException(QueryPlanException::NOT_SELECT, $e->getMessage());
        }

        try {
            $output = $driver->explain($sql, $bindings);
        } catch (HiddenTableException $e) {
            throw new QueryPlanException(QueryPlanException::HIDDEN_TABLE, $e->getMessage());
        } catch (QueryException $e) {
            throw $this->queryError($e);
        }

        $clauses = new SqlClauses($sql);
        $plan = $this->parsers->for($driver, $output)->parse($output, $sql);
        $tables = $this->indexReader->read($driver, array_values(array_unique([...$plan->tables(), ...array_values($clauses->tables())])));

        $this->alertDetector->detect($plan, $clauses, $tables);

        $fingerprint = QueryFingerprint::of($sql, $projectId);
        $previous = $conversationId === null ? null : $this->repository->latest($conversationId, $fingerprint)?->normalized();
        $comparison = $previous === null ? null : $this->comparator->compare($plan, $previous);

        $this->explainer->explain($plan, $bindings);

        return $this->repository->store($projectId, $conversationId, $fingerprint, [
            ...$plan->toArray(),
            'query' => $sql,
            'bindings' => $bindings,
            'indexes' => array_map(fn (array $table): array => ['indexes' => $table['indexes']], array_intersect_key($tables, array_flip($plan->tables()))),
            'previous' => $previous === null ? null : [
                'planId' => $previous['planId'],
                'createdAt' => $previous['createdAt'],
                'totals' => $previous['totals'],
                'nodes' => $previous['nodes'],
                'classic' => $previous['classic'],
                'raw' => $previous['raw'],
                'indexes' => $previous['indexes'] ?? [],
            ],
            'comparison' => $comparison,
        ]);
    }

    /**
     * L'erreur du moteur, classée pour que l'interface et l'agent sachent quoi corriger.
     */
    private function queryError(QueryException $e): QueryPlanException
    {
        $message = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        return match (true) {
            in_array($state, ['42S02', '42P01'], true) || str_contains($message, 'no such table') => new QueryPlanException(QueryPlanException::UNKNOWN_TABLE, $message),
            in_array($state, ['42000', '42601', '42S22', '42703'], true) || str_contains($message, 'syntax error') || str_contains($message, 'no such column') => new QueryPlanException(QueryPlanException::SYNTAX_ERROR, $message),
            default => new QueryPlanException(QueryPlanException::OTHER, $message),
        };
    }
}
