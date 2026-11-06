<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Le résumé du plan envoyé à l'agent, à la place du plan complet : assez court pour qu'un petit modèle local
 * le lise sans perdre le fil, et assez précis (tables, colonnes, index existants) pour proposer un index juste.
 */
final class PlanSummarizer
{
    private const string TEMP_TABLE_RULE = "Un index ne supprime une table temporaire de GROUP BY que si les colonnes du GROUP BY sont en tête de l'index et que le filtre le permet.";

    /**
     * @param  array<string, mixed>  $plan  Le plan normalisé, tel qu'il est stocké.
     * @return array{success: true, planId: string, driver: string, totalCost: ?float, steps: list<string>, alerts: list<array<string, mixed>>, existingIndexes: array<string, list<string>>, comparison: ?array<string, mixed>, instructions: string}
     */
    public function summarize(array $plan): array
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
        $parent = $node['parentId'] === null ? null : $context['nodes'][$node['parentId']];
        $inputRows = $this->inputRows($node, $context);

        return match ($node['operation']) {
            'full_scan' => match (true) {
                in_array('JOIN_WITHOUT_INDEX', $codes, true) => "Parcours complet de {$table} pour chaque ligne de la jointure".$this->rowsRead($node).$share,
                in_array('FULL_SCAN', $codes, true) => "Parcours complet de {$table}".$this->rowsRead($node)
                    .match (true) {
                        $node['estimatedRows'] !== null && $node['estimatedRows'] !== $node['rowsExamined'] => ', '.PlanFormat::rows($node['estimatedRows']).' gardées'.($condition === null ? '' : " par le filtre {$condition}"),
                        $condition !== null => ", filtre {$condition}",
                        default => '',
                    }.$share,
                ($parent['operation'] ?? null) === 'hash' => "Parcours complet de {$table}".($node['rowsExamined'] === null ? '' : ' ('.PlanFormat::rows($node['rowsExamined']).' lignes)').' pour construire une table de hachage'.($share === '' ? ' (normal)' : substr($share, 0, -1).', normal)'),
                default => "Parcours complet de {$table}".($node['rowsExamined'] === null ? '' : ' ('.PlanFormat::rows($node['rowsExamined']).' lignes, sans filtre)').$share,
            },
            'index_full_scan', 'range_scan', 'index_lookup', 'unique_lookup' => $node['label'].($node['index'] === null ? '' : " sur l'index {$node['index']}").($node['badge'] === 'index couvrant' ? ' (index couvrant)' : '')." de {$table}".$this->rowsRead($node).$share,
            'hash' => null,
            'limit' => $context['hasSort'] ? 'Limite aux '.($condition ?? $node['estimatedRows']).' premières lignes' : null,
            'group' => 'Regroupement'.($condition === null ? '' : " par {$condition}").(in_array('TEMP_TABLE', $codes, true) ? ' dans une table temporaire' : '').$share,
            'sort' => 'Tri'.($inputRows === null ? '' : ' de '.PlanFormat::rows($inputRows).' lignes').($condition === null ? '' : " par {$condition}").$share,
            'nested_loop', 'hash_join', 'merge_join' => $node['label'].($condition === null ? '' : " sur {$condition}").$share,
            'distinct' => 'Dédoublonnage'.(in_array('TEMP_TABLE', $codes, true) ? ' dans une table temporaire' : '').$share,
            'subquery' => 'Sous-requête'.($node['subtitle'] === null ? '' : " {$node['subtitle']}").(in_array('DEPENDENT_SUBQUERY', $codes, true) ? ', recalculée pour chaque ligne' : '').$share,
            'result' => $this->resultStep($node, $context),
            default => $node['label'].($node['subtitle'] === null ? '' : " : {$node['subtitle']}").$share,
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

        return $child !== null && $child['outputLabel'] !== null ? 'Résultat : '.$child['outputLabel'] : null;
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
            'FULL_SCAN' => "Parcours complet de {$table}".($columns === '' ? ' avec un filtre' : " pour filtrer sur {$columns}"),
            'JOIN_WITHOUT_INDEX' => "Jointure sans index sur {$table}".($columns === '' ? '' : " ({$columns})"),
            'TEMP_TABLE' => 'Table temporaire pour '.($node['operation'] === 'distinct' ? 'DISTINCT' : 'GROUP BY').($columns === '' ? '' : " {$columns}"),
            'FILESORT', 'SORT' => 'Tri'.($inputRows === null ? '' : ' de '.PlanFormat::rows($inputRows).' lignes').($columns === '' ? ' sans index' : " sur {$columns}"),
            'DEPENDENT_SUBQUERY' => 'Sous-requête recalculée pour chaque ligne'.($alert['table'] === null ? '' : " de {$table}"),
            'LARGE_ESTIMATE' => PlanFormat::rows((int) $node['rowsExamined'])." lignes lues par l'index {$node['index']} de {$table}",
            default => (string) $alert['message'],
        };
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @return array<string, mixed>
     */
    private function comparison(array $comparison): array
    {
        $describe = fn (array $alerts): array => array_map(fn (array $alert): string => $alert['code'].($alert['table'] === null ? '' : ' sur '.$alert['table']), $alerts);

        return [
            'previousPlanId' => $comparison['previousPlanId'],
            'costBefore' => $comparison['costBefore'],
            'costAfter' => $comparison['costAfter'],
            'costChange' => $comparison['costChange'] === null ? null : str_replace('−', '-', PlanFormat::change((float) $comparison['costChange'])),
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
            $plan['comparison'] !== null => 'Compare avec le plan précédent : dis ce qui a été résolu et ce qui reste, chiffres à l\'appui. Ne prétends pas qu\'une alerte restante a disparu.',
            $alerts === [] => 'Explique le plan à partir de ces étapes. Aucune alerte : ne propose pas d\'index sans raison.',
            default => 'Explique le plan à partir de ces étapes. Propose des index concrets (CREATE INDEX) pour les alertes, sans les créer toi-même, et jamais un index déjà présent dans existingIndexes.',
        };

        return $instructions.($groupTemporaryTable ? ' '.self::TEMP_TABLE_RULE : '');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function share(array $node): string
    {
        return $node['costShare'] !== null && $node['costShare'] > 0 ? ' ('.PlanFormat::share((float) $node['costShare']).' du coût)' : '';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function rowsRead(array $node): string
    {
        return $node['rowsExamined'] === null ? '' : ' : '.PlanFormat::rows((int) $node['rowsExamined']).' lignes lues';
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
