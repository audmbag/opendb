<?php

use App\Services\QueryPlan\Parsers\MySQLPlanParser;
use App\Services\QueryPlan\PlanAlertDetector;
use App\Services\QueryPlan\SqlClauses;

it('flags the filesort and the filtered full scan of a join explained by MySQL', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mysql-join-filesort');
    $plan = new MySQLPlanParser()->parse($output, $query);

    new PlanAlertDetector()->detect($plan, new SqlClauses($query), []);

    expect(array_map(fn (array $alert): array => [$alert['code'], $alert['table'], $alert['columns']], $plan->alerts()))->toBe([
        ['FILESORT', 'orders', ['total']],
        ['FULL_SCAN', 'orders', ['status', 'customer_id']],
    ]);
});
