<?php

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\Parsers\PostgresPlanParser;
use App\Support\TranslatableMessage;

it('reads the plan tree with the own cost of each step, out of the cost that includes its children', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('postgres-join');

    $plan = new PostgresPlanParser()->parse($output, $query);

    [$result, $limit, $sort, $join, $orders, $hash, $customers] = $plan->nodes;
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'limit', 'sort', 'hash_join', 'full_scan', 'hash', 'full_scan'])
        ->and($plan->driverLabel)->toBe('PostgreSQL 16')
        ->and($result->cost)->toBe(2987.41)
        ->and([$limit->subtitle, $limit->selfCost])->toBe(['LIMIT 20', 0.0])
        ->and([$sort->subtitle, $sort->table, $sort->selfCost, $sort->costShare])->toBe(['o.created_at DESC', 'orders', 845.31, 0.283])
        ->and([$join->subtitle, $join->selfCost])->toBe(['o.customer_id = c.id', 406.1])
        ->and($hash->table)->toBe('customers');
    expect([$orders->rowsExamined, $orders->estimatedRows, $orders->badge, $orders->condition['value']])->toEqual([60000, 21400, new TranslatableMessage('query_plan.badges.no_usable_index'), "(status)::text = 'paid'::text"])
        ->and([$customers->rowsExamined, $customers->badge])->toBe([5200, null]);
});

it('presents a sub-plan computed for each row as a dependent subquery', function () {
    $raw = [['Plan' => [
        'Node Type' => 'Seq Scan', 'Relation Name' => 'customers', 'Alias' => 'c', 'Total Cost' => 900.0, 'Plan Rows' => 10,
        'Filter' => '((SubPlan 1) > 1)',
        'Plans' => [['Node Type' => 'Aggregate', 'Strategy' => 'Plain', 'Parent Relationship' => 'SubPlan', 'Subplan Name' => 'SubPlan 1', 'Total Cost' => 30.0, 'Plan Rows' => 1,
            'Plans' => [['Node Type' => 'Seq Scan', 'Relation Name' => 'orders', 'Alias' => 'o', 'Total Cost' => 29.0, 'Plan Rows' => 3, 'Filter' => '(customer_id = c.id)']]]],
    ]]];

    $plan = new PostgresPlanParser()->parse(new ExplainOutput($raw, ['columns' => null, 'rows' => null, 'text' => ''], '16.4'), 'SELECT * FROM customers c WHERE (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) > 1');

    $subquery = $plan->nodes[2];
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'full_scan', 'subquery', 'group', 'full_scan'])
        ->and([$subquery->subtitle, $subquery->facts['dependent']])->toBe(['SubPlan 1', true]);
});
