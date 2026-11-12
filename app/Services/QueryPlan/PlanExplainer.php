<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;
use App\Support\TranslatableMessage;

/**
 * Les phrases d'explication et les lignes du panneau de détail de chaque étape, écrites à partir de modèles
 * pour rester stables et exactes. En comparaison, elles citent aussi les chiffres d'avant.
 */
final class PlanExplainer
{
    /**
     * @param  array<string, mixed>  $bindings
     */
    public function explain(Plan $plan, array $bindings): void
    {
        foreach ($plan->nodes as $node) {
            $node->explanation = $this->explanation($plan, $node);
            $node->details = $this->details($plan, $node, $bindings);
        }
    }

    /**
     * @return list<TranslatableMessage>
     */
    private function explanation(Plan $plan, PlanNode $node): array
    {
        $engine = $plan->engine;
        $key = $node->facts['key'] ?? null;
        $inputRows = $plan->inputRows($node) ?? $node->estimatedRows;
        $parent = $plan->parent($node);
        $index = $node->index ?? '';
        $message = fn (string $key, array $params = []): TranslatableMessage => new TranslatableMessage("query_plan.explanations.{$key}", ['engine' => $engine, ...$params]);
        $keyParam = fn (string $fallback): string|TranslatableMessage => $key ?? new TranslatableMessage("query_plan.explanations.{$fallback}");

        $sentence = match ($node->operation) {
            PlanOperation::Result => $plan->totals()['alerts']['danger'] + $plan->totals()['alerts']['warn'] > 0
                ? $message('result_with_alerts')
                : $message('result'),
            PlanOperation::FullScan => match (true) {
                $this->hasAlert($node, 'JOIN_WITHOUT_INDEX') => $message('full_scan_join', ['table' => (string) $node->table]),
                $this->hasAlert($node, 'FULL_SCAN') => $node->rowsExamined === null
                    ? $message('full_scan_filter_all')
                    : $message('full_scan_filter', ['rows' => TranslatableMessage::number($node->rowsExamined, 'rows')]),
                $parent?->operation === PlanOperation::Hash => $message('full_scan_hash'),
                default => $message('full_scan'),
            },
            PlanOperation::IndexFullScan => $message('index_full_scan', ['index' => $index]),
            PlanOperation::RangeScan => $message('range_scan', ['index' => $index]),
            PlanOperation::IndexLookup => $message('index_lookup', ['index' => $index]),
            PlanOperation::UniqueLookup => $message('unique_lookup', ['index' => $index]),
            PlanOperation::NestedLoop => $message('nested_loop'),
            PlanOperation::HashJoin => $message('hash_join'),
            PlanOperation::MergeJoin => $message('merge_join'),
            PlanOperation::Hash => $node->table === null ? $message('hash') : $message('hash_table', ['table' => $node->table]),
            PlanOperation::Group => match (true) {
                $node->facts['temporary'] ?? false => $message('group_temporary', ['key' => $keyParam('group_key')]),
                ($node->facts['strategy'] ?? null) === 'Hashed' => $message('group_hashed', ['key' => $keyParam('key')]),
                $key === null => $message('group_all'),
                default => $message('group_ordered', ['key' => $key]),
            },
            PlanOperation::Sort => $inputRows === null
                ? $message('sort', ['key' => $keyParam('sort_key')])
                : $message('sort_rows', ['rows' => TranslatableMessage::number($inputRows, 'rows'), 'key' => $keyParam('sort_key')]),
            PlanOperation::Distinct => ($node->facts['temporary'] ?? false) ? $message('distinct_temporary') : $message('distinct'),
            PlanOperation::Limit => $message('limit', ['limit' => (string) ($node->facts['limit'] ?? $node->estimatedRows ?? '')]),
            PlanOperation::Subquery => ($node->facts['dependent'] ?? false) ? $message('dependent_subquery') : $message('subquery'),
            PlanOperation::Materialize => $message('materialize'),
            PlanOperation::Union => $message('union'),
            PlanOperation::Other => $message('other', ['label' => $node->label]),
        };

        $covering = in_array($node->operation, [PlanOperation::IndexFullScan, PlanOperation::RangeScan, PlanOperation::IndexLookup], true)
            && ($node->facts['covering'] ?? false);

        return array_values(array_filter([
            $sentence,
            $covering ? $message('covering') : null,
            $this->comparisonSentence($plan, $node),
        ]));
    }

    /**
     * En comparaison, ce qui a changé pour cette étape : une alerte qui reste avec moins de lignes, ou des lignes lues en moins.
     */
    private function comparisonSentence(Plan $plan, PlanNode $node): ?TranslatableMessage
    {
        if ($node->before === null) {
            return null;
        }

        $remaining = array_find($node->alerts, fn (array $alert): bool => ($alert['status'] ?? null) === 'remaining');
        $inputRows = $plan->inputRows($node);
        $inputBefore = $node->before['inputRows'];

        if ($remaining !== null && $inputRows !== null && $inputBefore !== null && $inputRows !== $inputBefore) {
            return new TranslatableMessage('query_plan.explanations.remaining_alert', [
                'rows' => TranslatableMessage::number($inputRows, 'rows'),
                'before' => TranslatableMessage::number($inputBefore, 'rows'),
            ]);
        }

        if ($node->operation->isRead() && $node->rowsExamined !== null && $node->before['rowsExamined'] !== null && $node->rowsExamined !== $node->before['rowsExamined']) {
            return new TranslatableMessage('query_plan.explanations.rows_before', [
                'rows' => TranslatableMessage::number((int) $node->before['rowsExamined'], 'rows'),
                'step' => TranslatableMessage::from($node->before['label']),
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return list<array{label: TranslatableMessage, value: string|TranslatableMessage, mono?: bool, tone?: string}>
     */
    private function details(Plan $plan, PlanNode $node, array $bindings): array
    {
        $before = $node->before;
        $label = fn (string $key): TranslatableMessage => new TranslatableMessage("query_plan.details.{$key}");
        $none = new TranslatableMessage('query_plan.details.none');

        if ($node->operation === PlanOperation::Result) {
            $totals = $plan->totals();
            $alerts = $totals['alerts']['danger'] + $totals['alerts']['warn'];
            $improved = $before !== null && $before['cost'] !== null && $node->cost !== null && $node->cost < $before['cost'];

            return array_values(array_filter([
                $node->cost === null ? null : ['label' => $label('total_cost'), 'value' => $this->number($node->cost, 'decimal'), ...($improved ? ['tone' => 'good'] : [])],
                $before === null || $before['cost'] === null ? null : ['label' => $label('before'), 'value' => $this->number((float) $before['cost'], 'decimal')],
                ['label' => $label('steps'), 'value' => (string) count($plan->nodes)],
                ['label' => $label('alerts'), 'value' => (string) $alerts],
                ...array_map(
                    fn (string $name, mixed $value): array => ['label' => $label('parameter'), 'value' => ':'.$name.' = '.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)), 'mono' => true],
                    array_keys($bindings),
                    $bindings,
                ),
            ]));
        }

        if ($node->operation->isRead()) {
            $possibleKeys = $node->facts['possibleKeys'] ?? null;
            $filtered = $node->facts['filtered'] ?? null;
            $alert = $node->hasAlert('danger');

            return array_values(array_filter([
                isset($node->facts['accessType']) ? ['label' => $label('access'), 'value' => (string) $node->facts['accessType'], 'mono' => true, ...($alert ? ['tone' => 'danger'] : [])] : null,
                $possibleKeys === null ? null : ['label' => $label('possible_keys'), 'value' => $possibleKeys === [] ? $none : implode(', ', $possibleKeys), 'mono' => $possibleKeys !== []],
                ['label' => $label('used_index'), 'value' => $node->index ?? $none, 'mono' => $node->index !== null],
                $node->rowsExamined === null ? null : ['label' => $label('rows_examined'), 'value' => $this->rowsWithBefore($node->rowsExamined, $before['rowsExamined'] ?? null)],
                $node->estimatedRows === null || $node->estimatedRows === $node->rowsExamined ? null : ['label' => $label('rows_kept'), 'value' => is_float($filtered)
                    ? new TranslatableMessage('query_plan.details.rows_filtered', ['rows' => TranslatableMessage::number($node->estimatedRows, 'rows'), 'share' => TranslatableMessage::number($filtered, 'decimal')])
                    : $this->number($node->estimatedRows, 'rows')],
                ...$this->costDetails($node),
            ]));
        }

        $inputRows = $plan->inputRows($node);
        $yesNo = fn (bool $value): TranslatableMessage => new TranslatableMessage($value ? 'common.yes' : 'common.no');

        return array_values(array_filter([
            isset($node->facts['temporary']) ? ['label' => $label('temporary_table'), 'value' => $yesNo((bool) $node->facts['temporary'])] : null,
            isset($node->facts['filesort']) && in_array($plan->driver, ['mysql', 'mariadb'], true) ? ['label' => $label('filesort'), 'value' => $yesNo((bool) $node->facts['filesort'])] : null,
            isset($node->facts['joinType']) ? ['label' => $label('type'), 'value' => (string) $node->facts['joinType']] : null,
            $inputRows === null ? null : ['label' => $label('input_rows'), 'value' => $this->rowsWithBefore($inputRows, $before['inputRows'] ?? null)],
            $node->estimatedRows === null ? null : ['label' => $label('output_rows'), 'value' => $this->number($node->estimatedRows, 'rows')],
            ...$this->costDetails($node),
        ]));
    }

    /**
     * @return list<array{label: TranslatableMessage, value: TranslatableMessage, tone?: string}>
     */
    private function costDetails(PlanNode $node): array
    {
        $label = new TranslatableMessage('query_plan.details.self_cost');

        if ($node->selfCost === null) {
            return $node->operation->isRead() || $node->operation === PlanOperation::Result
                ? []
                : [['label' => new TranslatableMessage('query_plan.details.cost'), 'value' => new TranslatableMessage('query_plan.details.cost_included')]];
        }

        if ($node->selfCost <= 0 && ! $node->operation->isRead()) {
            return [];
        }

        $beforeCost = $node->before['selfCost'] ?? null;
        $cost = TranslatableMessage::number($node->selfCost, 'decimal');
        $value = $node->costShare === null
            ? $this->number($node->selfCost, 'decimal')
            : new TranslatableMessage('query_plan.details.cost_share', ['cost' => $cost, 'share' => TranslatableMessage::number($node->costShare, 'percent')]);

        if ($beforeCost === null || abs($beforeCost - $node->selfCost) < 0.01) {
            return [['label' => $label, 'value' => $value]];
        }

        return [[
            'label' => $label,
            'value' => new TranslatableMessage('query_plan.details.with_before', ['value' => $value, 'before' => TranslatableMessage::number((float) $beforeCost, 'decimal')]),
            'tone' => $node->selfCost < $beforeCost ? 'good' : 'danger',
        ]];
    }

    private function rowsWithBefore(int $rows, ?int $before): TranslatableMessage
    {
        return $before === null
            ? $this->number($rows, 'rows')
            : new TranslatableMessage('query_plan.details.with_before', ['value' => $this->number($rows, 'rows'), 'before' => TranslatableMessage::number($before, 'rows')]);
    }

    /**
     * A number alone, written by the reader in its language.
     */
    private function number(int|float $number, string $format): TranslatableMessage
    {
        return new TranslatableMessage('query_plan.details.number', ['number' => TranslatableMessage::number($number, $format)]);
    }

    private function hasAlert(PlanNode $node, string $code): bool
    {
        return array_any($node->alerts, fn (array $alert): bool => $alert['code'] === $code);
    }
}
