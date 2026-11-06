<?php

use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\Parsers\MySQLJsonV2PlanParser;

it('recognizes the second version of the JSON format by its root operation', function () {
    expect(MySQLJsonV2PlanParser::supports(queryPlanFixture('mysql-v2-hash-join')['output']->raw))->toBeTrue()
        ->and(MySQLJsonV2PlanParser::supports(queryPlanFixture('mysql-group-full-scan')['output']->raw))->toBeFalse();
});

it('reads a hash join and folds each filter into the table scan below it', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mysql-v2-hash-join');

    $plan = new MySQLJsonV2PlanParser()->parse($output, $query);

    [$result, $join, $orders, $customers] = $plan->nodes;
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'hash_join', 'full_scan', 'full_scan'])
        ->and($plan->driverLabel)->toBe('MySQL 8.4')
        ->and($result->cost)->toBe(1772.0)
        ->and($join->subtitle)->toBe('c.city = o.city')
        ->and($join->selfCost)->toBe(1665.25)
        ->and($customers->facts['joinCondition'])->toBe('c.city = o.city');
    expect([$orders->table, $orders->rowsExamined, $orders->estimatedRows, $orders->condition['value']])->toBe(['orders', 1000, 333, 'o.total > 100']);
});
