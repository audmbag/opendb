<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Support\TranslatableMessage;
use Illuminate\Support\Traits\Localizable;

/**
 * Le résumé du plan envoyé à l'agent, à la place du plan complet : assez court pour qu'un petit modèle local
 * le lise sans perdre le fil, et assez précis (tables, colonnes, index existants) pour proposer un index juste.
 * Il est écrit en anglais, comme toutes les instructions de l'agent, qui répond dans la langue de l'interface.
 */
final class PlanSummarizer
{
    use Localizable;

    private const string TEMP_TABLE_RULE = 'An index only removes a GROUP BY temporary table when the GROUP BY columns lead the index and the filter allows it.';

    /**
     * @param  array<string, mixed>  $plan  Le plan normalisé, tel qu'il est stocké.
     * @return array{success: true, planId: string, driver: string, totalCost: ?float, steps: list<string>, alerts: list<array<string, mixed>>, existingIndexes: array<string, list<string>>, comparison: ?array<string, mixed>, instructions: string}
     */
    public function summarize(array $plan): array
    {
        return $this->withLocale('en', fn (): array => $this->summary($plan));
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{success: true, planId: string, driver: string, totalCost: ?float, steps: list<string>, alerts: list<array<string, mixed>>, existingIndexes: array<string, list<string>>, comparison: ?array<string, mixed>, instructions: string}
     */
    private function summary(array $plan): array
    {
        $nodes = array_column($plan['nodes'], null, 'id');
        $children = [];

        foreach ($plan['nodes'] as $node) {
            if ($node['parentId'] !== null) {
                $children[$node['parentId']][] = $node['id'];
            }
        }

        $context = ['nodes' => $nodes, 'children' => $children, 'hasSort' => in_array('sort', array_column($plan['nodes'], 'operation'), true)];
        $order = $this->executionOrder((string) $plan['nodes'][0]['id'], $children);
        $steps = array_values(array_filter(array_map(
            fn (string $id): ?string => $this->step($nodes[$id], $context),
            $order,
        )));

        $alerts = array_merge(...array_map(fn (array $node): array => array_map(
            fn (array $alert): array => [
                'code' => $alert['code'],
                'severity' => $alert['severity'],
                'table' => $alert['table'],
                'columns' => $alert['columns'],
                'message' => $this->alertMessage($alert, $node, $context),
                ...(isset($alert['status']) ? ['status' => $alert['status']] : []),
            ],
            $node['alerts'],
        ), array_map(fn (string $id): array => $nodes[$id], $order)));

        usort($alerts, fn (array $first, array $second): int => ($first['severity'] === 'danger' ? 0 : 1) <=> ($second['severity'] === 'danger' ? 0 : 1));

        return [
            'success' => true,
            'planId' => (string) $plan['planId'],
            'driver' => (string) $plan['driver'],
            'totalCost' => $plan['totals']['cost'],
            'steps' => array_map(fn (string $step, int $index): string => ($index + 1).'. '.$step, $steps, array_keys($steps)),
            'alerts' => $alerts,
            'existingIndexes' => array_map(
                fn (array $table): array => array_map(fn (array $index): string => $index['name'].' ('.implode(', ', $index['columns']).')', $table['indexes']),
                (array) ($plan['indexes'] ?? []),
            ),
            'comparison' => $plan['comparison'] === null ? null : $this->comparison($plan['comparison']),
            'instructions' => $this->instructions($plan, $alerts),
        ];
    }

    /**
     * @param  array<string, list<string>>  $children
     * @return list<string>
     */
    private function executionOrder(string $id, array $children): array
    {
        return [...array_merge(...array_map(fn (string $child): array => $this->executionOrder($child, $children), $children[$id] ?? [])), $id];
    }

    /**
     * Une phrase par étape utile, avec ses chiffres. Les tables de hachage et les limites sans tri sont omises.
     *
     * @param  array<string, mixed>  $node
     * @param  array{nodes: array<string, array<string, mixed>>, children: array<string, list<string>>, hasSort: bool}  $context
     */
    private function step(array $node, array $context): ?string
    {
        $table = $node['table'];
        $codes = array_column($node['alerts'], 'code');
        $share = $this->share($node);
        $condition = $node['condition']['value'] ?? null;
        $label = TranslatableMessage::text($node['label']);
        $subtitle = $node['subtitle'] === null ? null : TranslatableMessage::text($node['subtitle']);
        $parent = $node['parentId'] === null ? null : $context['nodes'][$node['parentId']];
        $inputRows = $this->inputRows($node, $context);

        return match ($node['operation']) {
            'full_scan' => match (true) {
                in_array('JOIN_WITHOUT_INDEX', $codes, true) => "Full scan of {$table} for each row of the join".$this->rowsRead($node).$share,
                in_array('FULL_SCAN', $codes, true) => "Full scan of {$table}".$this->rowsRead($node)
                    .match (true) {
                        $node['estimatedRows'] !== null && $node['estimatedRows'] !== $node['rowsExamined'] => ', '.PlanFormat::rows($node['estimatedRows']).' kept'.($condition === null ? '' : " by the filter {$condition}"),
                        $condition !== null => ", filter {$condition}",
                        default => '',
                    }.$share,
                ($parent['operation'] ?? null) === 'hash' => "Full scan of {$table}".($node['rowsExamined'] === null ? '' : ' ('.PlanFormat::rows($node['rowsExamined']).' rows)').' to build a hash table'.($share === '' ? ' (normal)' : substr($share, 0, -1).', normal)'),
                default => "Full scan of {$table}".($node['rowsExamined'] === null ? '' : ' ('.PlanFormat::rows($node['rowsExamined']).' rows, no filter)').$share,
            },
            'index_full_scan', 'range_scan', 'index_lookup', 'unique_lookup' => $label.($node['index'] === null ? '' : " on the index {$node['index']}").($this->isCovering($node) ? ' (covering index)' : '')." of {$table}".$this->rowsRead($node).$share,
            'hash' => null,
            'limit' => $context['hasSort'] ? 'Limit to the first '.($condition ?? $node['estimatedRows']).' rows' : null,
            'group' => 'Grouping'.($condition === null ? '' : " by {$condition}").(in_array('TEMP_TABLE', $codes, true) ? ' in a temporary table' : '').$share,
            'sort' => 'Sort'.($inputRows === null ? '' : ' of '.PlanFormat::rows($inputRows).' rows').($condition === null ? '' : " by {$condition}").$share,
            'nested_loop', 'hash_join', 'merge_join' => $label.($condition === null ? '' : " on {$condition}").$share,
            'distinct' => 'Deduplication'.(in_array('TEMP_TABLE', $codes, true) ? ' in a temporary table' : '').$share,
            'subquery' => 'Subquery'.($subtitle === null ? '' : " {$subtitle}").(in_array('DEPENDENT_SUBQUERY', $codes, true) ? ', computed again for each row' : '').$share,
            'result' => $this->resultStep($node, $context),
            default => $label.($subtitle === null ? '' : ": {$subtitle}").$share,
        };
    }

    /**
     * Le résultat n'est cité que quand le nombre de lignes renvoyées n'a pas d'autre étape pour le dire (regroupement).
     *
     * @param  array<string, mixed>  $node
     * @param  array{nodes: array<string, array<string, mixed>>, children: array<string, list<string>>, hasSort: bool}  $context
     */
    private function resultStep(array $node, array $context): ?string
    {
        $children = $context['children'][$node['id']] ?? [];
        $child = count($children) === 1 ? $context['nodes'][$children[0]] : null;

        return $child !== null && $child['outputLabel'] !== null ? 'Result: '.TranslatableMessage::text($child['outputLabel']) : null;
    }

    /**
     * Whether a read finds every column in its index, said by its badge: a code since the messages are translatable,
     * the French text in the plans saved before.
     *
     * @param  array<string, mixed>  $node
     */
    private function isCovering(array $node): bool
    {
        $badge = $node['badge'] ?? null;

        return is_array($badge) ? ($badge['key'] ?? null) === 'query_plan.badges.covering_index' : $badge === 'index couvrant';
    }

    /**
     * @param  array<string, mixed>  $alert
     * @param  array<string, mixed>  $node
     * @param  array{nodes: array<string, array<string, mixed>>, children: array<string, list<string>>, hasSort: bool}  $context
     */
    private function alertMessage(array $alert, array $node, array $context): string
    {
        $table = $alert['table'] ?? '?';
        $columns = implode(', ', $alert['columns']);
        $inputRows = $this->inputRows($node, $context);

        return match ($alert['code']) {
            'FULL_SCAN' => "Full scan of {$table}".($columns === '' ? ' with a filter' : " to filter on {$columns}"),
            'JOIN_WITHOUT_INDEX' => "Join without index on {$table}".($columns === '' ? '' : " ({$columns})"),
            'TEMP_TABLE' => 'Temporary table for '.($node['operation'] === 'distinct' ? 'DISTINCT' : 'GROUP BY').($columns === '' ? '' : " {$columns}"),
            'FILESORT', 'SORT' => 'Sort'.($inputRows === null ? '' : ' of '.PlanFormat::rows($inputRows).' rows').($columns === '' ? ' without index' : " on {$columns}"),
            'DEPENDENT_SUBQUERY' => 'Subquery computed again for each row'.($alert['table'] === null ? '' : " of {$table}"),
            'LARGE_ESTIMATE' => PlanFormat::rows((int) $node['rowsExamined'])." rows read through the index {$node['index']} of {$table}",
            default => TranslatableMessage::text($alert['message']),
        };
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @return array<string, mixed>
     */
    private function comparison(array $comparison): array
    {
        $describe = fn (array $alerts): array => array_map(fn (array $alert): string => $alert['code'].($alert['table'] === null ? '' : ' on '.$alert['table']), $alerts);

        return [
            'previousPlanId' => $comparison['previousPlanId'],
            'costBefore' => $comparison['costBefore'],
            'costAfter' => $comparison['costAfter'],
            'costChange' => $comparison['costChange'] === null ? null : PlanFormat::change((float) $comparison['costChange']),
            'rowsReadBefore' => $comparison['rowsReadBefore'],
            'rowsReadAfter' => $comparison['rowsReadAfter'],
            'resolvedAlerts' => $describe($comparison['resolvedAlerts']),
            'remainingAlerts' => $describe($comparison['remainingAlerts']),
            'newAlerts' => $describe($comparison['newAlerts']),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $alerts
     */
    private function instructions(array $plan, array $alerts): string
    {
        $groupTemporaryTable = array_any($alerts, fn (array $alert): bool => $alert['code'] === 'TEMP_TABLE' && str_contains($alert['message'], 'GROUP BY'));

        $instructions = match (true) {
            $plan['comparison'] !== null => 'Compare with the previous plan: say what was resolved and what remains, with the numbers. Do not claim that a remaining alert is gone.',
            $alerts === [] => 'Explain the plan from these steps. No alert: do not suggest an index without a reason.',
            default => 'Explain the plan from these steps. Suggest concrete indexes (CREATE INDEX) for the alerts, without creating them yourself, and never an index already listed in existingIndexes.',
        };

        return $instructions.($groupTemporaryTable ? ' '.self::TEMP_TABLE_RULE : '');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function share(array $node): string
    {
        return $node['costShare'] !== null && $node['costShare'] > 0 ? ' ('.PlanFormat::share((float) $node['costShare']).' of the cost)' : '';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function rowsRead(array $node): string
    {
        return $node['rowsExamined'] === null ? '' : ': '.PlanFormat::rows((int) $node['rowsExamined']).' rows read';
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{nodes: array<string, array<string, mixed>>, children: array<string, list<string>>, hasSort: bool}  $context
     */
    private function inputRows(array $node, array $context): ?int
    {
        $children = array_map(fn (string $id): array => $context['nodes'][$id], $context['children'][$node['id']] ?? []);

        if ($children === [] || array_any($children, fn (array $child): bool => $child['estimatedRows'] === null)) {
            return null;
        }

        return (int) array_sum(array_column($children, 'estimatedRows'));
    }
}
