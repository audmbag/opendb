<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\PlanOperation;
use App\Services\QueryPlan\SqlClauses;
use App\Support\TranslatableMessage;

/**
 * Le plan de MySQL 8.3 et plus quand explain_json_format_version vaut 2 : un arbre d'opérations (operation, access_type, inputs)
 * avec un coût cumulé (estimated_total_cost) et des lignes produites (estimated_rows) sur chaque nœud.
 */
class MySQLJsonV2PlanParser extends BasePlanParser
{
    /**
     * Le plan est dans cette version quand sa racine décrit une opération au lieu d'un query_block.
     */
    public static function supports(mixed $raw): bool
    {
        return is_array($raw) && ! isset($raw['query_block']) && (isset($raw['operation']) || isset($raw['json_schema_version']));
    }

    public function parse(ExplainOutput $output, string $query): Plan
    {
        $this->clauses = new SqlClauses($query);
        $top = $this->node((array) $output->raw);

        return new Plan(
            driver: 'mysql',
            driverLabel: 'MySQL '.$this->majorMinor($output->version),
            engine: 'MySQL',
            hasCosts: true,
            root: $this->resultNode($top->cost, [$top]),
            classic: $output->classic,
            raw: $output->raw,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function node(array $raw): PlanNode
    {
        $children = array_map(fn (array $input): PlanNode => $this->node($input), array_values(array_filter((array) ($raw['inputs'] ?? []), is_array(...))));
        $operationText = (string) ($raw['operation'] ?? '');
        $cost = $this->float($raw['estimated_total_cost'] ?? null);
        $rows = $this->int($raw['estimated_rows'] ?? null);

        return match ($raw['access_type'] ?? null) {
            'table' => $this->read($raw, PlanOperation::FullScan, $children),
            'index' => $this->read($raw, $this->indexOperation((string) ($raw['index_access_type'] ?? '')), $children),
            'filter' => $this->filter($raw, $children),
            'join' => $this->join($raw, $children),
            'aggregate', 'temp_table_aggregate' => $this->group($raw, $children),
            'sort' => $this->sort($raw, $children),
            'limit' => new PlanNode(
                operation: PlanOperation::Limit,
                subtitle: isset($raw['limit']) ? 'LIMIT '.$raw['limit'] : null,
                estimatedRows: $rows,
                cost: $cost,
                selfCost: $this->selfCost($cost, $children),
                condition: isset($raw['limit']) ? ['label' => new TranslatableMessage('query_plan.conditions.row_count'), 'value' => (string) $raw['limit']] : null,
                facts: ['limit' => $this->int($raw['limit'] ?? null)],
                children: $children,
            ),
            'materialize', 'temp_table' => new PlanNode(PlanOperation::Materialize, subtitle: $raw['table_name'] ?? null, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), facts: ['temporary' => true], children: $children),
            'union', 'append' => new PlanNode(PlanOperation::Union, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), children: $children),
            'stream' => count($children) === 1 ? $children[0] : new PlanNode(PlanOperation::Other, $operationText, estimatedRows: $rows, cost: $cost, children: $children),
            default => match (true) {
                str_starts_with($operationText, 'Remove duplicates') => new PlanNode(PlanOperation::Distinct, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), facts: ['temporary' => str_contains($operationText, 'temporary')], children: $children),
                default => new PlanNode(PlanOperation::Other, $operationText !== '' ? $operationText : (isset($raw['access_type']) ? (string) $raw['access_type'] : null), estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), children: $children),
            },
        };
    }

    protected function indexOperation(string $indexAccessType): PlanOperation
    {
        return match ($indexAccessType) {
            'index_scan' => PlanOperation::IndexFullScan,
            'index_range_scan', 'index_skip_scan', 'group_index_skip_scan', 'dynamic_index_range_scan', 'multi_range_read' => PlanOperation::RangeScan,
            'index_lookup', 'full_text_search', 'pushed_join_ref' => PlanOperation::IndexLookup,
            'single_row_lookup', 'unique_lookup', 'const' => PlanOperation::UniqueLookup,
            default => PlanOperation::RangeScan,
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<PlanNode>  $children
     */
    protected function read(array $raw, PlanOperation $operation, array $children): PlanNode
    {
        $alias = (string) ($raw['table_name'] ?? $raw['alias'] ?? '?');
        $name = $this->clauses->tableFor($alias) ?? $alias;
        $cost = $this->float($raw['estimated_total_cost'] ?? null);
        $rows = $this->int($raw['estimated_rows'] ?? null);
        $covering = (bool) ($raw['covering'] ?? false);
        $lookup = $raw['lookup_condition'] ?? (isset($raw['ranges']) ? implode(' OR ', (array) $raw['ranges']) : null);

        return new PlanNode(
            operation: $operation,
            subtitle: $name,
            table: $name,
            index: isset($raw['index_name']) ? (string) $raw['index_name'] : null,
            badge: $this->readBadge($operation, $covering, isset($raw['possible_keys']) ? (array) $raw['possible_keys'] : null),
            rowsExamined: $rows,
            estimatedRows: $rows,
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: $lookup === null ? null : ['label' => new TranslatableMessage('query_plan.conditions.index_condition'), 'value' => SqlClauses::readable((string) $lookup)],
            facts: ['accessType' => (string) ($raw['index_access_type'] ?? $raw['access_type']), 'alias' => $alias, 'covering' => $covering, 'exactFilter' => true],
            children: $children,
        );
    }

    /**
     * Un filtre posé directement sur une lecture est rattaché à cette lecture : elle examine ses lignes et n'en garde qu'une partie.
     *
     * @param  array<string, mixed>  $raw
     * @param  list<PlanNode>  $children
     */
    protected function filter(array $raw, array $children): PlanNode
    {
        $condition = SqlClauses::readable((string) ($raw['condition'] ?? preg_replace('/^Filter:\s*/', '', (string) ($raw['operation'] ?? ''))));
        $read = count($children) === 1 && $children[0]->operation->isRead() ? $children[0] : null;

        if ($read !== null && ($read->facts['filter'] ?? null) === null) {
            $read->estimatedRows = $this->int($raw['estimated_rows'] ?? null) ?? $read->estimatedRows;
            $read->cost = $this->float($raw['estimated_total_cost'] ?? null) ?? $read->cost;
            $read->selfCost = $this->selfCost($read->cost, $read->children);
            $read->facts['filter'] = $condition;
            $read->condition = ['label' => new TranslatableMessage('query_plan.conditions.filter'), 'value' => $condition];

            return $read;
        }

        $cost = $this->float($raw['estimated_total_cost'] ?? null);

        return new PlanNode(PlanOperation::Other, new TranslatableMessage('query_plan.operations.filter'), subtitle: $condition, estimatedRows: $this->int($raw['estimated_rows'] ?? null), cost: $cost, selfCost: $this->selfCost($cost, $children), condition: ['label' => new TranslatableMessage('query_plan.conditions.filter'), 'value' => $condition], children: $children);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<PlanNode>  $children
     */
    protected function join(array $raw, array $children): PlanNode
    {
        $algorithm = (string) ($raw['join_algorithm'] ?? '');
        $condition = isset($raw['hash_condition'])
            ? implode(' AND ', array_map(fn (mixed $part): string => SqlClauses::readable((string) $part), (array) $raw['hash_condition']))
            : (preg_match('/\((.*)\)\s*$/', (string) ($raw['operation'] ?? ''), $matches) ? SqlClauses::readable($matches[1]) : null);
        $cost = $this->float($raw['estimated_total_cost'] ?? null);

        foreach (array_slice($children, 1) as $inner) {
            $inner->facts['joinCondition'] ??= $condition;
        }

        return new PlanNode(
            operation: match ($algorithm) {
                'hash' => PlanOperation::HashJoin,
                'merge' => PlanOperation::MergeJoin,
                default => PlanOperation::NestedLoop,
            },
            subtitle: $condition,
            estimatedRows: $this->int($raw['estimated_rows'] ?? null),
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: $condition === null ? null : ['label' => new TranslatableMessage('query_plan.conditions.join_condition'), 'value' => $condition],
            facts: ['joinType' => $raw['join_type'] ?? null],
            children: $children,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<PlanNode>  $children
     */
    protected function sort(array $raw, array $children): PlanNode
    {
        $key = $this->fields($raw['sort_fields'] ?? null) ?? $this->clauses->orderBy();
        $cost = $this->float($raw['estimated_total_cost'] ?? null);

        return new PlanNode(
            operation: PlanOperation::Sort,
            subtitle: $key,
            estimatedRows: $this->int($raw['estimated_rows'] ?? null),
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: $this->keyCondition('query_plan.conditions.sort_key', $key),
            facts: ['filesort' => true, 'key' => $key],
            children: $children,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<PlanNode>  $children
     */
    protected function group(array $raw, array $children): PlanNode
    {
        $key = $this->fields($raw['group_items'] ?? null) ?? $this->clauses->groupBy();
        $cost = $this->float($raw['estimated_total_cost'] ?? null);
        $rows = $this->int($raw['estimated_rows'] ?? null);

        return new PlanNode(
            operation: PlanOperation::Group,
            subtitle: $key === null ? null : 'GROUP BY '.$key,
            estimatedRows: $rows,
            outputLabel: $rows === null && $key !== null ? new TranslatableMessage('query_plan.output.one_row_per', ['key' => $key]) : null,
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: $this->keyCondition('query_plan.conditions.group_key', $key),
            facts: ['temporary' => ($raw['access_type'] ?? null) === 'temp_table_aggregate' || str_contains((string) ($raw['operation'] ?? ''), 'temporary'), 'key' => $key],
            children: $children,
        );
    }

    protected function fields(mixed $fields): ?string
    {
        return is_array($fields) && $fields !== [] ? implode(', ', array_map(fn (mixed $field): string => SqlClauses::readable((string) $field), $fields)) : null;
    }
}
