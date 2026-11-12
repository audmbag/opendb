<?php

use App\Ai\Tools\Database\ExplainQuery;
use App\Models\QueryPlan;
use App\Models\User;
use App\Services\QueryPlan\QueryPlanManager;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('explain-query-').'.sqlite';
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * The tool of a project whose database is the small shop, in a conversation of its owner.
 */
function explainQueryTool(string $databasePath, bool $inConversation = true): ExplainQuery
{
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $databasePath, shopDatabase(...));
    $conversationId = $inConversation ? $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id])->id : null;

    return new ExplainQuery($project->driver_config->getDriver(), app(QueryPlanManager::class), $project->id, $conversationId);
}

/**
 * @param  array<string, mixed>  $bindings
 * @return array<string, mixed>
 */
function explainWith(ExplainQuery $tool, string $query, array $bindings = []): array
{
    return json_decode((string) $tool->handle(new Request(['query' => $query, 'bindings' => $bindings])), true);
}

it('summarizes the plan for the agent and flags a full scan filtered on a column', function () {
    $tool = explainQueryTool($this->databasePath);

    $summary = explainWith($tool, 'SELECT * FROM orders WHERE status = :status', ['status' => 'paid']);

    expect($summary)->toMatchArray([
        'success' => true,
        'driver' => 'sqlite',
        'totalCost' => null,
        'steps' => ['1. Full scan of orders, filter status = :status'],
        'alerts' => [[
            'code' => 'FULL_SCAN',
            'severity' => 'danger',
            'table' => 'orders',
            'columns' => ['status'],
            'message' => 'Full scan of orders to filter on status',
        ]],
        'existingIndexes' => ['orders' => ['primary (id)']],
        'comparison' => null,
    ])->and($summary['planId'])->toStartWith('qp_');
});

it('stores the plan as it was when the agent asked for it', function () {
    $tool = explainQueryTool($this->databasePath);

    $summary = explainWith($tool, 'SELECT * FROM orders WHERE status = :status', ['status' => 'paid']);

    $plan = QueryPlan::findOrFail($summary['planId']);
    expect($plan->conversation_id)->toBe($tool->conversationId)
        ->and($plan->payload['query'])->toBe('SELECT * FROM orders WHERE status = :status')
        ->and($plan->payload['bindings'])->toBe(['status' => 'paid'])
        ->and(array_column($plan->payload['nodes'], 'operation'))->toBe(['result', 'full_scan']);
});

it('does not flag a search through the primary key', function () {
    $tool = explainQueryTool($this->databasePath);

    $summary = explainWith($tool, 'SELECT * FROM orders WHERE id = :id', ['id' => 1]);

    expect($summary['alerts'])->toBe([])
        ->and($summary['steps'])->toBe(['1. Unique lookup on the index PRIMARY KEY of orders']);
});

it('compares the plan with the previous plan of the same query once an index is created', function () {
    $tool = explainQueryTool($this->databasePath);
    $first = explainWith($tool, 'SELECT * FROM orders WHERE status = :status', ['status' => 'paid']);
    $tool->driver->getConnection()->statement('CREATE INDEX orders_status_index ON orders (status)');

    $second = explainWith($tool, "select *\n  from orders where status = :status", ['status' => 'pending']);

    expect($second['comparison'])->toBe([
        'previousPlanId' => $first['planId'],
        'costBefore' => null,
        'costAfter' => null,
        'costChange' => null,
        'rowsReadBefore' => 0,
        'rowsReadAfter' => 0,
        'resolvedAlerts' => ['FULL_SCAN on orders'],
        'remainingAlerts' => [],
        'newAlerts' => [],
    ])->and($second['existingIndexes'])->toBe(['orders' => ['orders_status_index (status)', 'primary (id)']]);
});

it('does not compare plans of a call made outside of a conversation', function () {
    $tool = explainQueryTool($this->databasePath, inConversation: false);
    explainWith($tool, 'SELECT * FROM orders WHERE status = :status', ['status' => 'paid']);

    $summary = explainWith($tool, 'SELECT * FROM orders WHERE status = :status', ['status' => 'paid']);

    expect($summary['comparison'])->toBeNull();
});

it('refuses a query that is not a SELECT without storing a plan', function () {
    $tool = explainQueryTool($this->databasePath);

    $result = explainWith($tool, 'EXPLAIN ANALYZE DELETE FROM orders');

    expect($result)->toMatchArray(['success' => false, 'error' => 'not_select']);
    expect(QueryPlan::count())->toBe(0);
    expect($tool->driver->getConnection()->table('orders')->count())->toBe(3);
});

it('reports a table that does not exist', function () {
    $tool = explainQueryTool($this->databasePath);

    $result = explainWith($tool, 'SELECT * FROM invoices');

    expect($result)->toMatchArray(['success' => false, 'error' => 'unknown_table'])
        ->and($result['message'])->toContain('invoices');
});
