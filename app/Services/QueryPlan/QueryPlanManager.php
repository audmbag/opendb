<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Database\Contracts\DriverContract;
use App\Models\QueryPlan;
use App\Repository\QueryPlanRepository;
use App\Services\QueryPlan\Exception\QueryPlanException;

/**
 * Passerelle entre l'application et les plans d'exécution : calcul et stockage, résumé pour l'agent, lecture pour l'interface.
 */
final readonly class QueryPlanManager
{
    public function __construct(
        private PlanAnalyzer $analyzer,
        private PlanSummarizer $summarizer,
        private QueryPlanRepository $repository,
    ) {}

    /**
     * Calcule le plan d'une requête SELECT sans l'exécuter, et le stocke tel quel pour que l'interface ne le recalcule jamais.
     *
     * @param  array<string, mixed>  $bindings
     *
     * @throws QueryPlanException Si la requête n'est pas un SELECT, si le moteur la refuse ou n'a pas de parser
     */
    public function explain(DriverContract $driver, string $projectId, ?string $conversationId, string $sql, array $bindings): QueryPlan
    {
        return $this->analyzer->analyze($driver, $projectId, $conversationId, $sql, $bindings);
    }

    /**
     * Le résumé du plan envoyé à l'agent à la place du plan complet.
     *
     * @return array<string, mixed>
     */
    public function summarize(QueryPlan $plan): array
    {
        return $this->summarizer->summarize($plan->normalized());
    }

    /**
     * Un plan stocké de la conversation, tel que l'interface l'affiche.
     *
     * @return ?array<string, mixed>
     */
    public function find(string $conversationId, string $planId): ?array
    {
        return $this->repository->find($conversationId, $planId)?->normalized();
    }
}
