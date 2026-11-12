<?php

use App\Database\ExplainOutput;
use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\Parsers\MySQLPlanParser;
use App\Support\TranslatableMessage;

it('reads a grouped full scan, with the cost of the read and the group key of the query', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mysql-group-full-scan');

    $plan = new MySQLPlanParser()->parse($output, $query);

    [$result, $group, $scan] = $plan->nodes;
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'group', 'full_scan'])
        ->and($plan->driverLabel)->toBe('MySQL 8.0')
        ->and($result->cost)->toBe(6059.25)
        ->and($group->subtitle)->toBe('GROUP BY product_id')
        ->and($group->outputLabel)->toEqual(new TranslatableMessage('query_plan.output.one_row_per', ['key' => 'product_id']))
        ->and($group->facts['temporary'])->toBeTrue()
        ->and($scan->parentId)->toBe($group->id);
    expect([$scan->table, $scan->badge, $scan->rowsExamined, $scan->estimatedRows, $scan->selfCost, $scan->costShare])
        ->toEqual(['product_views', new TranslatableMessage('query_plan.badges.no_index'), 60030, 20008, 6059.25, 1.0])
        ->and($scan->condition)->toEqual(['label' => new TranslatableMessage('query_plan.conditions.filter'), 'value' => 'viewed_at >= (now() - interval 7 day)']);
});

it('reads a covering range scan on an index', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mysql-group-range-scan');

    $scan = array_last(new MySQLPlanParser()->parse($output, $query)->nodes);

    expect([$scan->operation->value, $scan->index, $scan->badge, $scan->rowsExamined])
        ->toEqual(['range_scan', 'idx_product_views_viewed_at_product_id', new TranslatableMessage('query_plan.badges.covering_index'), 8412]);
});

it('joins the tables of a nested loop in execution order, the inner table read through the join key', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mysql-join-filesort');

    $plan = new MySQLPlanParser()->parse($output, $query);

    [, $sort, $join, $orders, $customers] = $plan->nodes;
    expect(array_map(fn (PlanNode $node): string => $node->operation->value, $plan->nodes))->toBe(['result', 'sort', 'nested_loop', 'full_scan', 'unique_lookup'])
        ->and($sort->subtitle)->toBe('o.total')
        ->and($sort->facts['filesort'])->toBeTrue()
        ->and($join->condition)->toEqual(['label' => new TranslatableMessage('query_plan.conditions.join_condition'), 'value' => 'c.id = o.customer_id'])
        ->and($orders->badge)->toEqual(new TranslatableMessage('query_plan.badges.no_usable_index'))
        ->and($orders->condition['value'])->toBe("(o.status = 'paid') and (o.customer_id is not null)");
    expect([$customers->table, $customers->rowsExamined, $customers->selfCost])->toBe(['customers', 188, 109.9]);
});

it('keeps an access type it does not know as a step named after it', function () {
    ['output' => $output, 'query' => $query] = queryPlanFixture('mysql-group-full-scan');
    $raw = $output->raw;
    $raw['query_block']['grouping_operation']['table']['access_type'] = 'skip_scan';

    $scan = array_last(new MySQLPlanParser()->parse(new ExplainOutput($raw, $output->classic, '8.0.36'), $query)->nodes);

    expect([$scan->operation->value, $scan->label])->toBe(['other', 'skip_scan']);
});
