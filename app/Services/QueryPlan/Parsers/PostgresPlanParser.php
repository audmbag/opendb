<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\PlanOperation;
use App\Services\QueryPlan\SqlClauses;

/**
 * Le plan de PostgreSQL renvoyé par EXPLAIN (FORMAT JSON) : un arbre Plan / Plans dont le Total Cost inclut celui des enfants.
 */
class PostgresPlanParser extends BasePlanParser
{
    /**
     * @var array<string, int>
     */
    protected array $tableRows = [];

    public function parse(ExplainOutput $output, string $query): Plan
    {
        $this->clauses = new SqlClauses($query);
        $this->tableRows = $output->tableRows;
        $top = $this->node((array) ($output->raw[0]['Plan'] ?? []));

        return new Plan(
            driver: 'pgsql',
            driverLabel: 'PostgreSQL '.(preg_match('/^(\d+)/', $output->version, $matches) ? $matches[1] : $output->version),
            engine: 'PostgreSQL',
            hasCosts: true,
            root: $this->resultNode($top->cost, [$top]),
            classic: $output->classic,
            raw: $output->raw,
        );
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function node(array $step): PlanNode
    {
        $type = (string) ($step['Node Type'] ?? '?');
        $plans = array_values(array_filter((array) ($step['Plans'] ?? []), is_array(...)));

        if ($type === 'Bitmap Heap Scan') {
            return $this->bitmapHeapScan($step, $plans);
        }

        $children = array_map(fn (array $child): PlanNode => $this->child($child), $plans);
        $cost = $this->float($step['Total Cost'] ?? null);
        $rows = $this->int($step['Plan Rows'] ?? null);

        return match ($type) {
            'Seq Scan', 'Index Scan', 'Index Only Scan' => $this->scan($step, $children),
            'Nested Loop', 'Hash Join', 'Merge Join' => $this->join($step, $children),
            'Hash' => new PlanNode(PlanOperation::Hash, subtitle: $this->firstTable($children), table: $this->firstTable($children), estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), children: $children),
            'Aggregate', 'HashAggregate', 'GroupAggregate' => $this->aggregate($step, $children),
            'Sort', 'Incremental Sort' => $this->keyed(PlanOperation::Sort, 'Clé de tri', $this->keys($step['Sort Key'] ?? null), $step, $children, ['filesort' => true]),
            'Unique' => new PlanNode(PlanOperation::Distinct, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), children: $children),
            'Limit' => new PlanNode(
                operation: PlanOperation::Limit,
                subtitle: 'LIMIT '.($this->clauses->limit() ?? $rows),
                estimatedRows: $rows,
                cost: $cost,
                selfCost: $this->selfCost($cost, $children),
                condition: ['label' => 'Nombre de lignes', 'value' => (string) ($this->clauses->limit() ?? $rows)],
                facts: ['limit' => $this->clauses->limit() ?? $rows],
                children: $children,
            ),
            'Subquery Scan' => new PlanNode(PlanOperation::Subquery, subtitle: $step['Alias'] ?? null, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), condition: $this->filterCondition($step), children: $children),
            'Materialize', 'CTE Scan' => new PlanNode(PlanOperation::Materialize, subtitle: $step['CTE Name'] ?? null, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), condition: $this->filterCondition($step), children: $children),
            'Append', 'Merge Append' => new PlanNode(PlanOperation::Union, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), children: $children),
            default => new PlanNode(PlanOperation::Other, $type, subtitle: $step['Relation Name'] ?? null, table: $step['Relation Name'] ?? null, estimatedRows: $rows, cost: $cost, selfCost: $this->selfCost($cost, $children), condition: $this->filterCondition($step), children: $children),
        };
    }

    /**
     * Un sous-plan (SubPlan, InitPlan) est présenté comme une sous-requête. Un SubPlan non haché est recalculé pour chaque ligne.
     *
     * @param  array<string, mixed>  $step
     */
    protected function child(array $step): PlanNode
    {
        $node = $this->node($step);
        $relationship = $step['Parent Relationship'] ?? null;

        if (! in_array($relationship, ['SubPlan', 'InitPlan'], true)) {
            return $node;
        }

        $name = (string) ($step['Subplan Name'] ?? $relationship);

        return new PlanNode(
            operation: PlanOperation::Subquery,
            subtitle: $name,
            estimatedRows: $node->estimatedRows,
            cost: $node->cost,
            selfCost: 0.0,
            facts: ['dependent' => $relationship === 'SubPlan' && ! str_starts_with($name, 'hashed')],
            children: [$node],
        );
    }

    /**
     * Une lecture de table. Le plan ne donne que les lignes gardées après le filtre : pour un parcours complet, les lignes
     * examinées viennent des statistiques de la table.
     *
     * @param  array<string, mixed>  $step
     * @param  list<PlanNode>  $children
     */
    protected function scan(array $step, array $children): PlanNode
    {
        $type = (string) $step['Node Type'];
        $table = (string) ($step['Relation Name'] ?? '?');
        $indexCondition = isset($step['Index Cond']) ? (string) $step['Index Cond'] : null;
        $operation = match (true) {
            $type === 'Seq Scan' => PlanOperation::FullScan,
            $indexCondition !== null && $this->isEquality($indexCondition) => PlanOperation::IndexLookup,
            default => PlanOperation::RangeScan,
        };
        $rows = $this->int($step['Plan Rows'] ?? null);
        $cost = $this->float($step['Total Cost'] ?? null);
        $filter = isset($step['Filter']) ? (string) $step['Filter'] : null;

        return new PlanNode(
            operation: $operation,
            subtitle: $table,
            table: $table,
            index: isset($step['Index Name']) ? (string) $step['Index Name'] : null,
            badge: match (true) {
                $type === 'Index Only Scan' => 'index couvrant',
                $operation === PlanOperation::FullScan && $filter !== null => 'aucun index utilisable',
                default => null,
            },
            rowsExamined: $operation === PlanOperation::FullScan ? ($this->tableRows[$table] ?? $rows) : $rows,
            estimatedRows: $rows,
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: match (true) {
                $filter !== null => ['label' => 'Filtre appliqué', 'value' => SqlClauses::unwrap($filter)],
                $indexCondition !== null => ['label' => "Condition d'index", 'value' => SqlClauses::unwrap($indexCondition)],
                default => null,
            },
            facts: [
                'accessType' => $type,
                'alias' => $step['Alias'] ?? $table,
                'filter' => $filter === null ? null : SqlClauses::unwrap($filter),
                'exactFilter' => true,
                'covering' => $type === 'Index Only Scan',
                'indexCondition' => $indexCondition,
            ],
            children: $children,
        );
    }

    /**
     * Un Bitmap Heap Scan lit la table à partir des positions trouvées par son Bitmap Index Scan, qui lui est rattaché.
     *
     * @param  array<string, mixed>  $step
     * @param  list<array<string, mixed>>  $plans
     */
    protected function bitmapHeapScan(array $step, array $plans): PlanNode
    {
        $indexScans = array_values(array_filter($plans, fn (array $plan): bool => ($plan['Node Type'] ?? null) === 'Bitmap Index Scan'));
        $others = array_values(array_filter($plans, fn (array $plan): bool => ($plan['Node Type'] ?? null) !== 'Bitmap Index Scan'));

        $node = $this->scan([...$step, 'Node Type' => 'Bitmap Heap Scan', 'Index Name' => $indexScans[0]['Index Name'] ?? null, 'Index Cond' => $step['Recheck Cond'] ?? null], array_map(fn (array $child): PlanNode => $this->child($child), $others));
        $node->operation = PlanOperation::RangeScan;
        $node->label = PlanOperation::RangeScan->label();
        $node->rowsExamined = $this->int($indexScans[0]['Plan Rows'] ?? null) ?? $node->rowsExamined;

        return $node;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  list<PlanNode>  $children
     */
    protected function join(array $step, array $children): PlanNode
    {
        $condition = $step['Hash Cond'] ?? $step['Merge Cond'] ?? $step['Join Filter'] ?? null;
        $condition = $condition === null ? $this->innerIndexCondition($children) : SqlClauses::unwrap((string) $condition);
        $cost = $this->float($step['Total Cost'] ?? null);

        foreach (array_slice($children, 1) as $inner) {
            $inner->facts['joinCondition'] ??= $condition;
        }

        return new PlanNode(
            operation: match ($step['Node Type']) {
                'Hash Join' => PlanOperation::HashJoin,
                'Merge Join' => PlanOperation::MergeJoin,
                default => PlanOperation::NestedLoop,
            },
            subtitle: $condition,
            estimatedRows: $this->int($step['Plan Rows'] ?? null),
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: $condition === null ? null : ['label' => 'Condition de jointure', 'value' => $condition],
            facts: ['joinType' => $step['Join Type'] ?? null],
            children: $children,
        );
    }

    /**
     * Dans une boucle imbriquée, la jointure est souvent la condition d'index de la table interne.
     *
     * @param  list<PlanNode>  $children
     */
    protected function innerIndexCondition(array $children): ?string
    {
        $inner = $children[1] ?? null;
        $condition = $inner?->facts['indexCondition'] ?? null;

        return is_string($condition) ? SqlClauses::unwrap($condition) : null;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  list<PlanNode>  $children
     */
    protected function aggregate(array $step, array $children): PlanNode
    {
        $node = $this->keyed(PlanOperation::Group, 'Clé de regroupement', $this->keys($step['Group Key'] ?? null), $step, $children, [
            'strategy' => $step['Strategy'] ?? ($step['Node Type'] === 'HashAggregate' ? 'Hashed' : null),
        ]);

        $node->subtitle = $node->subtitle === null ? null : 'GROUP BY '.$node->subtitle;

        return $node;
    }

    /**
     * Une étape définie par une clé (tri, regroupement).
     *
     * @param  array<string, mixed>  $step
     * @param  list<PlanNode>  $children
     * @param  array<string, mixed>  $facts
     */
    protected function keyed(PlanOperation $operation, string $label, ?string $key, array $step, array $children, array $facts): PlanNode
    {
        $cost = $this->float($step['Total Cost'] ?? null);

        return new PlanNode(
            operation: $operation,
            subtitle: $key,
            table: $this->keyTable($key),
            estimatedRows: $this->int($step['Plan Rows'] ?? null),
            cost: $cost,
            selfCost: $this->selfCost($cost, $children),
            condition: $this->keyCondition($label, $key),
            facts: [...$facts, 'key' => $key],
            children: $children,
        );
    }

    protected function keyTable(?string $key): ?string
    {
        $qualifier = array_first(array_filter(array_column(SqlClauses::columnReferences((string) $key), 'table')));

        return $this->clauses->tableFor($qualifier);
    }

    protected function keys(mixed $keys): ?string
    {
        return is_array($keys) && $keys !== [] ? implode(', ', array_map(strval(...), $keys)) : null;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return ?array{label: string, value: string}
     */
    protected function filterCondition(array $step): ?array
    {
        return isset($step['Filter']) ? ['label' => 'Filtre appliqué', 'value' => SqlClauses::unwrap((string) $step['Filter'])] : null;
    }

    /**
     * Une condition d'index qui ne compare que par égalité désigne des valeurs précises, pas un intervalle.
     */
    protected function isEquality(string $condition): bool
    {
        return str_contains($condition, '=') && ! preg_match('/<|>|~~|\bANY\b|\bIS\b/i', $condition);
    }

    /**
     * @param  list<PlanNode>  $children
     */
    protected function firstTable(array $children): ?string
    {
        foreach ($children as $child) {
            if ($child->table !== null) {
                return $child->table;
            }
        }

        return null;
    }
}
