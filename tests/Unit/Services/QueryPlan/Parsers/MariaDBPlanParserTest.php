<?php

use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\Parsers\MariaDBPlanParser;

it('turns the filesort of a query grouped without ORDER BY into the group step, with its temporary table', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mariadb-group-join-buffer');

    $plan = new MariaDBPlanParser()->parse($output, $query);

    [, $group, $join, $customers, $orders] = $plan->nodes;
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'group', 'nested_loop', 'full_scan', 'full_scan'])
        ->and([$plan->driverLabel, $plan->hasCosts])->toBe(['MariaDB 10.11', false])
        ->and($group->subtitle)->toBe('GROUP BY c.city')
        ->and([$group->facts['temporary'], $group->facts['filesort']])->toBe([true, true])
        ->and($join->condition['value'])->toBe('o.status = c.name')
        ->and($customers->table)->toBe('customers');
    expect([$orders->table, $orders->facts['joinBuffer'], $orders->rowsExamined])->toBe(['orders', true, 50000]);
});
