<?php

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\Parsers\SQLitePlanParser;

/**
 * @param  list<array{0: int, 1: int, 2: string}>  $rows  id, parent and detail of each line of EXPLAIN QUERY PLAN.
 */
function sqlitePlanOutput(array $rows): ExplainOutput
{
    return new ExplainOutput(array_map(fn (array $row): array => ['id' => $row[0], 'parent' => $row[1], 'notused' => 0, 'detail' => $row[2]], $rows), ['columns' => ['id', 'parent', 'detail'], 'rows' => [], 'text' => null], '3.45.1');
}

it('joins sibling reads in loop order and sorts their rows above the join', function () {
    $output = sqlitePlanOutput([[3, 0, 'SCAN o'], [5, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)'], [9, 0, 'USE TEMP B-TREE FOR ORDER BY']]);

    $plan = new SQLitePlanParser()->parse($output, 'SELECT * FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.total > 3 ORDER BY o.total');

    [, $sort, $join, $orders, $customers] = $plan->nodes;
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'sort', 'nested_loop', 'full_scan', 'unique_lookup'])
        ->and([$plan->driverLabel, $plan->hasCosts])->toBe(['SQLite 3.45', false])
        ->and([$sort->subtitle, $sort->facts['filesort']])->toBe(['o.total', true])
        ->and($join->subtitle)->toBe('c.rowid=?')
        ->and([$orders->table, $orders->condition['value']])->toBe(['orders', 'o.total > 3'])
        ->and([$customers->table, $customers->index])->toBe(['customers', 'PRIMARY KEY']);
});

it('nests a correlated subquery and a derived table under the steps that use them', function () {
    $correlated = new SQLitePlanParser()->parse(
        sqlitePlanOutput([[2, 0, 'SCAN c'], [4, 0, 'CORRELATED SCALAR SUBQUERY 1'], [7, 4, 'SCAN o']]),
        'SELECT * FROM customers c WHERE (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) > 1',
    );
    $derived = new SQLitePlanParser()->parse(
        sqlitePlanOutput([[2, 0, 'CO-ROUTINE s'], [5, 2, 'SCAN customers'], [8, 2, 'USE TEMP B-TREE FOR GROUP BY'], [20, 0, 'SCAN s']]),
        'SELECT * FROM (SELECT city, COUNT(*) n FROM customers GROUP BY city) s WHERE n > 1',
    );

    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $correlated->nodes))->toBe(['result', 'full_scan', 'subquery', 'full_scan'])
        ->and($correlated->nodes[2]->facts['dependent'])->toBeTrue()
        ->and(array_map(fn (PlanNode $node): string => $node->operation->value, $derived->nodes))->toBe(['result', 'full_scan', 'subquery', 'group', 'full_scan']);
});

it('keeps a line it does not know as a step named after it', function () {
    $plan = new SQLitePlanParser()->parse(sqlitePlanOutput([[2, 0, 'BLOOM FILTER ON o (customer_id=?)']]), 'SELECT * FROM orders o');

    expect([$plan->nodes[1]->operation->value, $plan->nodes[1]->label])->toBe(['other', 'BLOOM FILTER ON o (customer_id=?)']);
});
