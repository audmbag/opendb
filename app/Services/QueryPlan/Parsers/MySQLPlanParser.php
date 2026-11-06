<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\PlanOperation;
use App\Services\QueryPlan\SqlClauses;

/**
 * Le plan de MySQL renvoyé par EXPLAIN FORMAT=JSON dans sa première version (query_block, nested_loop, table…).
 */
class MySQLPlanParser extends BasePlanParser
{
    /**
     * Les clés qui portent des sous-requêtes rattachées à l'étape qui les contient.
     */
    protected const array SUBQUERY_KEYS = ['attached_subqueries', 'select_list_subqueries', 'having_subqueries', 'optimized_away_subqueries', 'subqueries'];

    public function parse(ExplainOutput $output, string $query): Plan
    {
        $this->clauses = new SqlClauses($query);
        $block = (array) ($output->raw['query_block'] ?? []);

        return new Plan(
            driver: $this->driver(),
            driverLabel: $this->engine().' '.$this->majorMinor($output->version),
            engine: $this->engine(),
            hasCosts: $this->totalCost($block) !== null,
            root: $this->resultNode($this->totalCost($block), $this->content($block, topLevel: true)),
            classic: $output->classic,
            raw: $output->raw,
        );
    }

    protected function driver(): string
    {
        return 'mysql';
    }

    protected function engine(): string
    {
        return 'MySQL';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function totalCost(array $block): ?float
    {
        return $this->float($block['cost_info']['query_cost'] ?? null);
    }

    /**
     * Les étapes d'une partie du plan : la première clé connue donne l'étape, les sous-requêtes s'y rattachent.
     *
     * @param  array<string, mixed>  $part
     * @return list<PlanNode>
     */
    protected function content(array $part, bool $topLevel = false): array
    {
        $nodes = [];

        foreach ($part as $key => $value) {
            if (! is_array($value) || in_array($key, self::SUBQUERY_KEYS, true)) {
                continue;
            }

            $found = $this->part((string) $key, $value, $topLevel);

            if ($found !== null) {
                $nodes = $found;

                break;
            }
        }

        $subqueries = $this->subqueries($part);

        if ($nodes === []) {
            return $subqueries;
        }

        $nodes[0]->children = [...$nodes[0]->children, ...$subqueries];

        return $nodes;
    }

    /**
     * Les étapes d'une clé du plan, ou null quand elle n'en décrit pas.
     *
     * @param  array<mixed>  $value
     * @return ?list<PlanNode>
     */
    protected function part(string $key, array $value, bool $topLevel): ?array
    {
        return match ($key) {
            'ordering_operation' => $this->ordering($value, $topLevel),
            'grouping_operation' => [$this->grouping($value, $topLevel)],
            'duplicates_removal' => [$this->distinct($value, $topLevel)],
            'windowing' => [new PlanNode(PlanOperation::Other, 'windowing', children: $this->content($value))],
            'buffer_result' => $this->content($value, $topLevel),
            'union_result' => [$this->union($value)],
            'nested_loop' => $this->join($value),
            'table' => [$this->read($value)],
            default => null,
        };
    }

    /**
     * Un tri n'apparaît que s'il est fait à part (filesort) : sinon l'ordre vient déjà d'un index.
     *
     * @param  array<string, mixed>  $value
     * @return list<PlanNode>
     */
    protected function ordering(array $value, bool $topLevel): array
    {
        $children = $this->content($value, $topLevel);

        if (! ($value['using_filesort'] ?? false) && ! ($value['using_temporary_table'] ?? false)) {
            return $children;
        }

        return [$this->sortNode($topLevel ? $this->clauses->orderBy() : null, $children, [
            'filesort' => (bool) ($value['using_filesort'] ?? false),
            'temporary' => (bool) ($value['using_temporary_table'] ?? false),
        ], $this->float($value['cost_info']['sort_cost'] ?? null))];
    }

    /**
     * @param  list<PlanNode>  $children
     * @param  array<string, mixed>  $facts
     */
    protected function sortNode(?string $key, array $children, array $facts, ?float $selfCost = null): PlanNode
    {
        $rows = count($children) === 1 ? $children[0]->estimatedRows : null;

        return new PlanNode(
            operation: PlanOperation::Sort,
            subtitle: $key,
            estimatedRows: $rows,
            cost: $selfCost === null ? null : $selfCost + array_sum(array_map(fn (PlanNode $child): float => (float) $child->cost, $children)),
            selfCost: $selfCost,
            condition: $this->keyCondition('Clé de tri', $key),
            facts: [...$facts, 'key' => $key],
            children: $children,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function grouping(array $value, bool $topLevel): PlanNode
    {
        return $this->groupNode($topLevel ? $this->clauses->groupBy() : null, $this->content($value), [
            'temporary' => (bool) ($value['using_temporary_table'] ?? false),
            'filesort' => (bool) ($value['using_filesort'] ?? false),
        ]);
    }

    /**
     * Le nombre de groupes n'est pas estimé par MySQL : l'arête affiche « 1 ligne par … ».
     *
     * @param  list<PlanNode>  $children
     * @param  array<string, mixed>  $facts
     */
    protected function groupNode(?string $key, array $children, array $facts): PlanNode
    {
        return new PlanNode(
            operation: PlanOperation::Group,
            subtitle: $key === null ? null : 'GROUP BY '.$key,
            outputLabel: $key === null ? 'une ligne par groupe' : '1 ligne par '.$key,
            condition: $this->keyCondition('Clé de regroupement', $key),
            facts: [...$facts, 'key' => $key],
            children: $children,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function distinct(array $value, bool $topLevel): PlanNode
    {
        return new PlanNode(
            operation: PlanOperation::Distinct,
            subtitle: $topLevel && $this->clauses->isDistinct() ? 'SELECT DISTINCT' : null,
            outputLabel: 'lignes distinctes',
            facts: ['temporary' => (bool) ($value['using_temporary_table'] ?? false)],
            children: $this->content($value),
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function union(array $value): PlanNode
    {
        $members = array_map(
            fn (array $specification): array => $this->content((array) ($specification['query_block'] ?? $specification)),
            array_values(array_filter((array) ($value['query_specifications'] ?? []), is_array(...))),
        );

        return new PlanNode(
            operation: PlanOperation::Union,
            subtitle: $value['table_name'] ?? null,
            facts: ['temporary' => (bool) ($value['using_temporary_table'] ?? false)],
            children: array_merge(...$members),
        );
    }

    /**
     * Les tables d'une boucle imbriquée, jointes deux à deux dans l'ordre d'exécution (table externe d'abord).
     *
     * @param  array<mixed>  $items
     * @return list<PlanNode>
     */
    protected function join(array $items): array
    {
        $current = null;
        $outerRows = null;
        $previousCost = 0.0;

        foreach (array_values(array_filter($items, is_array(...))) as $item) {
            [$table, $joinBuffer] = $this->joinedTable($item);

            if ($table === null) {
                $nodes = $this->content($item);
                $current = $current === null ? ($nodes[0] ?? null) : $this->joinNode($current, $nodes[0] ?? null, false, null);

                continue;
            }

            $read = $this->read($table, $outerRows, $previousCost, inner: $current !== null, joinBuffer: $joinBuffer);
            $current = $current === null ? $read : $this->joinNode($current, $read, $this->isHashJoin($item), $read->facts['joinCondition'] ?? null);

            $outerRows = $this->int($table['rows_produced_per_join'] ?? null) ?? $read->estimatedRows;
            $previousCost = $read->cost ?? $previousCost;
        }

        return $current === null ? [] : [$current];
    }

    /**
     * La table d'un élément de boucle imbriquée, et si elle est lue avec un tampon de jointure.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: ?array<string, mixed>, 1: bool}
     */
    protected function joinedTable(array $item): array
    {
        $table = $item['table'] ?? null;

        return [is_array($table) ? $table : null, is_array($table) && isset($table['using_join_buffer'])];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isHashJoin(array $item): bool
    {
        return str_contains(mb_strtolower((string) ($item['table']['using_join_buffer'] ?? '')), 'hash');
    }

    protected function joinNode(PlanNode $outer, ?PlanNode $inner, bool $hash, ?string $condition): PlanNode
    {
        $children = array_values(array_filter([$outer, $inner]));
        $last = array_last($children);

        return new PlanNode(
            operation: $hash ? PlanOperation::HashJoin : PlanOperation::NestedLoop,
            subtitle: $condition,
            estimatedRows: $last?->estimatedRows,
            cost: $last?->cost,
            selfCost: $last?->cost === null ? null : 0.0,
            condition: $condition === null ? null : ['label' => 'Condition de jointure', 'value' => $condition],
            children: $children,
        );
    }

    /**
     * La lecture d'une table. Les lignes examinées d'une table interne sont multipliées par les lignes de la table externe,
     * et son coût propre est l'écart entre son coût cumulé (prefix_cost) et celui de la table précédente.
     *
     * @param  array<string, mixed>  $table
     */
    protected function read(array $table, ?int $outerRows = null, float $previousCost = 0.0, bool $inner = false, bool $joinBuffer = false): PlanNode
    {
        $accessType = isset($table['access_type']) ? (string) $table['access_type'] : null;
        $operation = $this->readOperation($accessType);
        $alias = (string) ($table['table_name'] ?? '?');
        $name = $this->clauses->tableFor($alias) ?? $alias;
        $perScan = $this->int($table['rows_examined_per_scan'] ?? $table['rows'] ?? null);
        $filtered = $this->float($table['filtered'] ?? null);
        $cost = $this->float($table['cost_info']['prefix_cost'] ?? null);
        $possibleKeys = match (true) {
            isset($table['possible_keys']) => array_values((array) $table['possible_keys']),
            $operation->isRead() => [],
            default => null,
        };
        $covering = (bool) ($table['using_index'] ?? false);

        [$joinCondition, $filter] = $this->joinAndFilter($table, $alias, $inner, $joinBuffer);

        return new PlanNode(
            operation: $operation,
            label: $operation === PlanOperation::Other ? ($accessType ?? 'table') : null,
            subtitle: $name,
            table: $name,
            index: isset($table['key']) ? (string) $table['key'] : null,
            badge: $this->readBadge($operation, $covering, $possibleKeys),
            rowsExamined: $perScan === null ? null : $perScan * max(1, $outerRows ?? 1),
            estimatedRows: $this->int($table['rows_produced_per_join'] ?? null) ?? ($perScan !== null && $filtered !== null ? $this->int($perScan * $filtered / 100 * max(1, $outerRows ?? 1)) : $perScan),
            cost: $cost,
            selfCost: $cost === null ? null : round(max(0.0, $cost - $previousCost), 2),
            condition: match (true) {
                $filter !== null => ['label' => 'Filtre appliqué', 'value' => $filter],
                isset($table['index_condition']) => ['label' => "Condition d'index", 'value' => SqlClauses::readable((string) $table['index_condition'])],
                default => null,
            },
            facts: [
                'accessType' => $accessType,
                'alias' => $alias,
                'possibleKeys' => $possibleKeys,
                'filter' => $filter,
                'exactFilter' => true,
                'filtered' => $filtered,
                'joinCondition' => $joinCondition,
                'joinBuffer' => $joinBuffer,
                'covering' => $covering,
                'key' => isset($table['used_key_parts']) ? implode(', ', (array) $table['used_key_parts']) : null,
            ],
            children: [...$this->derivedTable($table), ...$this->subqueries($table)],
        );
    }

    protected function readOperation(?string $accessType): PlanOperation
    {
        return match ($accessType) {
            'ALL' => PlanOperation::FullScan,
            'index' => PlanOperation::IndexFullScan,
            'range', 'index_merge' => PlanOperation::RangeScan,
            'ref', 'ref_or_null', 'fulltext', 'index_subquery' => PlanOperation::IndexLookup,
            'eq_ref', 'const', 'system', 'unique_subquery' => PlanOperation::UniqueLookup,
            default => PlanOperation::Other,
        };
    }

    /**
     * La condition de jointure d'une table interne et le filtre qui reste. Avec un index, la jointure se lit dans ref ;
     * avec un tampon de jointure, la condition attachée à la table est la jointure elle-même.
     *
     * @param  array<string, mixed>  $table
     * @return array{0: ?string, 1: ?string}
     */
    protected function joinAndFilter(array $table, string $alias, bool $inner, bool $joinBuffer): array
    {
        $attached = isset($table['attached_condition']) ? SqlClauses::readable((string) $table['attached_condition']) : null;

        if (! $inner) {
            return [null, $this->withoutSingleTablePrefix($attached, $alias)];
        }

        $references = array_values(array_filter((array) ($table['ref'] ?? []), fn (mixed $ref): bool => is_string($ref) && $ref !== 'const' && ! str_starts_with($ref, 'func')));

        if ($references !== [] && ! $joinBuffer) {
            $parts = array_values((array) ($table['used_key_parts'] ?? []));
            $condition = implode(' AND ', array_map(
                fn (string $ref, int $position): string => $alias.'.'.($parts[$position] ?? '?').' = '.implode('.', array_slice(explode('.', $ref), -2)),
                $references,
                array_keys($references),
            ));

            return [$condition, $attached];
        }

        return [$attached, null];
    }

    /**
     * Le filtre d'une requête sur une seule table, sans le préfixe de table que MySQL ajoute à chaque colonne.
     */
    protected function withoutSingleTablePrefix(?string $condition, string $alias): ?string
    {
        if ($condition === null || count(array_unique($this->clauses->tables())) > 1) {
            return $condition;
        }

        return (string) preg_replace('/(?<![\w.])'.preg_quote($alias, '/').'\.(?=\w)/', '', $condition);
    }

    /**
     * La sous-requête qui remplit une table dérivée (FROM (SELECT …) alias).
     *
     * @param  array<string, mixed>  $table
     * @return list<PlanNode>
     */
    protected function derivedTable(array $table): array
    {
        $derived = $table['materialized_from_subquery'] ?? null;

        if (! is_array($derived)) {
            return [];
        }

        return [new PlanNode(
            operation: PlanOperation::Subquery,
            subtitle: (string) ($table['table_name'] ?? ''),
            facts: ['dependent' => (bool) ($derived['dependent'] ?? false), 'temporary' => (bool) ($derived['using_temporary_table'] ?? false)],
            children: $this->content((array) ($derived['query_block'] ?? $derived)),
        )];
    }

    /**
     * Les sous-requêtes rattachées à une partie du plan (WHERE, liste du SELECT, HAVING).
     *
     * @param  array<string, mixed>  $part
     * @return list<PlanNode>
     */
    protected function subqueries(array $part): array
    {
        $nodes = [];

        foreach (self::SUBQUERY_KEYS as $key) {
            foreach (array_filter((array) ($part[$key] ?? []), is_array(...)) as $subquery) {
                $block = (array) ($subquery['query_block'] ?? $subquery['subquery']['query_block'] ?? $subquery);

                $nodes[] = new PlanNode(
                    operation: PlanOperation::Subquery,
                    subtitle: isset($block['select_id']) ? 'select #'.$block['select_id'] : null,
                    facts: ['dependent' => (bool) ($subquery['dependent'] ?? false)],
                    children: $this->content($block),
                );
            }
        }

        return $nodes;
    }
}
