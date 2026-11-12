<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;
use App\Support\TranslatableMessage;

/**
 * Compare un plan au précédent plan de la même requête dans la conversation : chaque étape reçoit l'étape correspondante
 * d'avant, chaque alerte son statut, et le plan les chiffres avant / après.
 */
final class PlanComparator
{
    /**
     * @param  array{planId: string, totals: array{cost: ?float, rowsRead: int}, nodes: list<array<string, mixed>>}  $previous
     * @return array{previousPlanId: string, costBefore: ?float, costAfter: ?float, costChange: ?float, rowsReadBefore: int, rowsReadAfter: int, resolvedAlerts: list<array{code: string, table: ?string}>, remainingAlerts: list<array{code: string, table: ?string}>, newAlerts: list<array{code: string, table: ?string}>}
     */
    public function compare(Plan $plan, array $previous): array
    {
        $this->matchNodes($plan, $previous['nodes']);

        $previousAlerts = $this->alertKeys(array_merge(...array_map(fn (array $node): array => (array) ($node['alerts'] ?? []), $previous['nodes'])));
        $currentAlerts = $this->alertKeys($plan->alerts());

        foreach ($plan->nodes as $node) {
            foreach ($node->alerts as $index => $alert) {
                $remaining = isset($previousAlerts[$this->alertKey($alert)]);
                $node->alerts[$index]['status'] = $remaining ? 'remaining' : 'new';
                $node->alerts[$index]['message'] = $remaining
                    ? new TranslatableMessage('query_plan.alerts.still_present', ['alert' => TranslatableMessage::from($alert['message'])])
                    : $alert['message'];
            }
        }

        $costBefore = $previous['totals']['cost'] ?? null;
        $costAfter = $plan->totalCost();

        return [
            'previousPlanId' => $previous['planId'],
            'costBefore' => $costBefore,
            'costAfter' => $costAfter,
            'costChange' => $costBefore !== null && $costAfter !== null && $costBefore > 0 ? round(($costAfter - $costBefore) / $costBefore, 2) : null,
            'rowsReadBefore' => (int) ($previous['totals']['rowsRead'] ?? 0),
            'rowsReadAfter' => $plan->rowsRead(),
            'resolvedAlerts' => array_values(array_diff_key($previousAlerts, $currentAlerts)),
            'remainingAlerts' => array_values(array_intersect_key($currentAlerts, $previousAlerts)),
            'newAlerts' => array_values(array_diff_key($currentAlerts, $previousAlerts)),
        ];
    }

    /**
     * Une lecture correspond à la lecture de la même table, une autre étape à la première étape de même opération.
     *
     * @param  list<array<string, mixed>>  $previousNodes
     */
    private function matchNodes(Plan $plan, array $previousNodes): void
    {
        $used = [];

        foreach ($plan->nodes as $node) {
            $match = array_find($previousNodes, fn (array $candidate): bool => ! in_array($candidate['id'], $used, true) && $this->corresponds($node, $candidate));

            if ($match === null) {
                continue;
            }

            $used[] = $match['id'];
            $children = array_filter($previousNodes, fn (array $candidate): bool => $candidate['parentId'] === $match['id']);

            $node->before = [
                'label' => TranslatableMessage::from($match['label']),
                'rowsExamined' => $match['rowsExamined'],
                'estimatedRows' => $match['estimatedRows'],
                'inputRows' => $children === [] || array_any($children, fn (array $child): bool => $child['estimatedRows'] === null)
                    ? null
                    : (int) array_sum(array_column($children, 'estimatedRows')),
                'cost' => $match['cost'],
                'selfCost' => $match['selfCost'],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function corresponds(PlanNode $node, array $candidate): bool
    {
        $operation = PlanOperation::tryFrom((string) $candidate['operation']) ?? PlanOperation::Other;

        if ($node->operation->isRead()) {
            return $operation->isRead() && $candidate['table'] === $node->table;
        }

        return $operation === $node->operation && ($node->table === null || $candidate['table'] === null || $candidate['table'] === $node->table);
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     * @return array<string, array{code: string, table: ?string}>
     */
    private function alertKeys(array $alerts): array
    {
        $keys = [];

        foreach ($alerts as $alert) {
            $keys[$this->alertKey($alert)] = ['code' => (string) $alert['code'], 'table' => $alert['table'] ?? null];
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function alertKey(array $alert): string
    {
        return $alert['code'].'|'.($alert['table'] ?? '');
    }
}
