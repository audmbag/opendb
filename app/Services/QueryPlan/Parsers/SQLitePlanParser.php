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
 * Le plan de SQLite renvoyé par EXPLAIN QUERY PLAN : des lignes id / parent / detail, sans coût ni estimation de lignes.
 * Les tables jointes sont des étapes sœurs dans l'ordre des boucles, et les B-tree temporaires (tri, regroupement)
 * des étapes sœurs elles aussi : elles sont réorganisées pour que les données remontent des tables vers le résultat.
 */
class SQLitePlanParser extends BasePlanParser
{
    /**
     * @var array<int, list<array{id: int, parent: int, detail: string}>>
     */
    protected array $steps = [];

    public function parse(ExplainOutput $output, string $query): Plan
    {
        $this->clauses = new SqlClauses($query);
        $this->steps = [];

        foreach ((array) $output->raw as $row) {
            $row = (array) $row;
            $this->steps[(int) $row['parent']][] = ['id' => (int) $row['id'], 'parent' => (int) $row['parent'], 'detail' => (string) $row['detail']];
        }

        return new Plan(
            driver: 'sqlite',
            driverLabel: 'SQLite '.$this->majorMinor($output->version),
            engine: 'SQLite',
            hasCosts: false,
            root: $this->resultNode(null, $this->level(0, topLevel: true)),
            classic: $output->classic,
            raw: $output->raw,
        );
    }

    /**
     * Les étapes sœurs sous un même parent, assemblées en un seul arbre : lectures jointes, puis regroupement,
     * dédoublonnage et tri, les sous-requêtes étant rattachées à l'étape du dessus.
     *
     * @return list<PlanNode>
     */
    protected function level(int $parent, bool $topLevel = false, bool $joinable = true): array
    {
        $sources = [];
        $derived = [];
        $subqueries = [];
        $temporaryTrees = [];

        foreach ($this->steps[$parent] ?? [] as $step) {
            $detail = $step['detail'];

            if (preg_match('/^(SCAN|SEARCH)\s/', $detail)) {
                $sources[] = $this->read($detail, $this->level($step['id']), $topLevel);
            } elseif (preg_match('/^USE TEMP B-TREE FOR (.+)$/', $detail, $matches)) {
                $temporaryTrees[] = $matches[1];
            } elseif (preg_match('/^(?:MATERIALIZE|CO-ROUTINE)\s+(.+)$/', $detail, $matches)) {
                $derived[mb_strtolower(trim($matches[1]))] = new PlanNode(
                    operation: str_starts_with($detail, 'MATERIALIZE') ? PlanOperation::Materialize : PlanOperation::Subquery,
                    subtitle: trim($matches[1]),
                    children: $this->level($step['id']),
                );
            } elseif (preg_match('/^(CORRELATED )?(?:SCALAR|LIST) SUBQUERY/', $detail, $matches)) {
                $subqueries[] = new PlanNode(PlanOperation::Subquery, subtitle: $detail, facts: ['dependent' => ($matches[1] ?? '') !== ''], children: $this->level($step['id']));
            } elseif ($detail === 'COMPOUND QUERY') {
                $sources[] = new PlanNode(PlanOperation::Union, children: $this->level($step['id'], joinable: false));
            } else {
                $sources[] = new PlanNode(PlanOperation::Other, $detail, children: $this->level($step['id'], joinable: ! str_starts_with($detail, 'MULTI-INDEX OR')));
            }
        }

        foreach ($sources as $source) {
            $name = mb_strtolower((string) ($source->facts['alias'] ?? ''));

            if ($name !== '' && isset($derived[$name])) {
                $source->children[] = $derived[$name];
                unset($derived[$name]);
            }
        }

        $nodes = [...array_values($derived), ...$sources];

        if ($nodes === []) {
            return $subqueries;
        }

        $top = $joinable ? $this->join($nodes) : null;
        $top = $top === null ? $nodes : [$top];

        if (count($top) === 1) {
            $top = [$this->withTemporaryTrees($top[0], $temporaryTrees, $topLevel)];
            $top[0]->children = [...$top[0]->children, ...$subqueries];

            return $top;
        }

        return [...$top, ...$subqueries];
    }

    /**
     * Les lectures sœurs jointes deux à deux dans l'ordre des boucles ; les autres étapes restent côte à côte.
     *
     * @param  list<PlanNode>  $nodes
     */
    protected function join(array $nodes): ?PlanNode
    {
        if (count($nodes) === 1) {
            return $nodes[0];
        }

        if (! array_all($nodes, fn (PlanNode $node): bool => $node->operation->isRead() || $node->operation === PlanOperation::Other)) {
            return null;
        }

        $current = array_shift($nodes);

        foreach ($nodes as $inner) {
            $condition = $inner->facts['indexCondition'] ?? null;
            $inner->facts['joinCondition'] = $condition;
            $current = new PlanNode(
                operation: PlanOperation::NestedLoop,
                subtitle: $condition,
                condition: $condition === null ? null : ['label' => new TranslatableMessage('query_plan.conditions.join_condition'), 'value' => $condition],
                children: [$current, $inner],
            );
        }

        return $current;
    }

    /**
     * Les B-tree temporaires, appliqués dans l'ordre d'exécution : regroupement, dédoublonnage, puis tri.
     *
     * @param  list<string>  $purposes
     */
    protected function withTemporaryTrees(PlanNode $node, array $purposes, bool $topLevel): PlanNode
    {
        $has = fn (string $purpose): bool => array_any($purposes, fn (string $candidate): bool => str_contains($candidate, $purpose));

        if ($has('GROUP BY')) {
            $key = $topLevel ? $this->clauses->groupBy() : null;
            $node = new PlanNode(
                operation: PlanOperation::Group,
                subtitle: $key === null ? null : 'GROUP BY '.$key,
                outputLabel: $key === null ? new TranslatableMessage('query_plan.output.one_row_per_group') : new TranslatableMessage('query_plan.output.one_row_per', ['key' => $key]),
                condition: $this->keyCondition('query_plan.conditions.group_key', $key),
                facts: ['temporary' => true, 'key' => $key],
                children: [$node],
            );
        }

        if ($has('DISTINCT')) {
            $node = new PlanNode(PlanOperation::Distinct, subtitle: 'SELECT DISTINCT', outputLabel: new TranslatableMessage('query_plan.output.distinct_rows'), facts: ['temporary' => true], children: [$node]);
        }

        if ($has('ORDER BY')) {
            $key = $topLevel ? $this->clauses->orderBy() : null;
            $node = new PlanNode(PlanOperation::Sort, subtitle: $key, condition: $this->keyCondition('query_plan.conditions.sort_key', $key), facts: ['filesort' => true, 'key' => $key], children: [$node]);
        }

        return $node;
    }

    /**
     * Une ligne SCAN ou SEARCH. SQLite ne dit pas quel filtre il applique : celui du WHERE est proposé, et l'alerte ne
     * le retient que s'il porte sur une colonne de cette table.
     *
     * @param  list<PlanNode>  $children
     */
    protected function read(string $detail, array $children, bool $topLevel): PlanNode
    {
        preg_match('/^(SCAN|SEARCH)\s+(?:TABLE\s+)?(\S+)(?:\s+AS\s+(\S+))?/', $detail, $matches);

        $alias = $matches[3] ?? $matches[2];
        $name = $this->clauses->tableFor($alias) ?? $this->clauses->tableFor($matches[2]) ?? $matches[2];
        $index = preg_match('/USING (?:[A-Z ]*?)INDEX (\S+)/', $detail, $indexMatch) ? $indexMatch[1] : null;
        $primaryKey = (bool) preg_match('/USING (?:INTEGER )?PRIMARY KEY/', $detail);
        $condition = preg_match('/\((.*)\)\s*$/', $detail, $conditionMatch) ? $conditionMatch[1] : null;
        $covering = str_contains($detail, 'COVERING INDEX');
        $equality = $condition !== null && ! preg_match('/[<>]/', $condition);

        $operation = match (true) {
            $matches[1] === 'SCAN' && $index === null && ! $primaryKey => PlanOperation::FullScan,
            $matches[1] === 'SCAN' => PlanOperation::IndexFullScan,
            $primaryKey && $equality => PlanOperation::UniqueLookup,
            $equality => PlanOperation::IndexLookup,
            default => PlanOperation::RangeScan,
        };

        $filter = $operation === PlanOperation::FullScan && $topLevel ? $this->clauses->where() : null;
        $indexCondition = $condition === null ? null : $this->indexCondition($alias, $condition);

        return new PlanNode(
            operation: $operation,
            subtitle: $name,
            table: $name,
            index: $index ?? ($primaryKey ? 'PRIMARY KEY' : null),
            badge: $this->readBadge($operation, $covering, $operation === PlanOperation::FullScan && $filter !== null ? [] : null),
            condition: match (true) {
                $filter !== null => ['label' => new TranslatableMessage('query_plan.conditions.filter'), 'value' => $filter],
                $indexCondition !== null => ['label' => new TranslatableMessage('query_plan.conditions.index_condition'), 'value' => $indexCondition],
                default => null,
            },
            facts: [
                'accessType' => $detail,
                'alias' => $alias,
                'filter' => $filter,
                'exactFilter' => false,
                'covering' => $covering,
                'indexCondition' => $indexCondition,
            ],
            children: $children,
        );
    }

    /**
     * La condition d'index de SQLite (« rowid=? », « status>? ») avec le nom de la table devant chaque colonne.
     */
    protected function indexCondition(string $alias, string $condition): string
    {
        return (string) preg_replace('/\b(\w+)(?=\s*(?:=|<|>|<=|>=)\s*\?)/', $alias.'.$1', $condition);
    }
}
