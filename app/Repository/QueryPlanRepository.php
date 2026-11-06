<?php

namespace App\Repository;

use App\Models\QueryPlan;

class QueryPlanRepository
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(string $projectId, ?string $conversationId, string $fingerprint, array $payload): QueryPlan
    {
        return QueryPlan::create([
            'project_id' => $projectId,
            'conversation_id' => $conversationId,
            'fingerprint' => $fingerprint,
            'payload' => $payload,
        ]);
    }

    /**
     * Le dernier plan de la même requête dans la conversation, s'il y en a un.
     */
    public function latest(string $conversationId, string $fingerprint): ?QueryPlan
    {
        return QueryPlan::query()
            ->where('conversation_id', $conversationId)
            ->where('fingerprint', $fingerprint)
            ->latest()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Un plan de la conversation : un plan d'une autre conversation n'est jamais renvoyé.
     */
    public function find(string $conversationId, string $planId): ?QueryPlan
    {
        return QueryPlan::query()->where('conversation_id', $conversationId)->whereKey($planId)->first();
    }
}
