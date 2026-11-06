<?php

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\MySQLDriver;
use App\Database\Drivers\PostgresSQLDriver;
use App\Database\ExplainOutput;
use App\Models\Project;
use App\Models\User;
use App\Services\QueryPlan\QueryPlanManager;
use Illuminate\Support\Str;

/**
 * A driver of the given class that answers EXPLAIN with the saved fixtures, one per call, without reaching a server.
 *
 * @param  class-string<MySQLDriver|PostgresSQLDriver>  $driverClass
 */
function driverExplainingFixtures(string $driverClass, string ...$fixtures): DriverContract
{
    $config = ['driver_id' => $driverClass === MySQLDriver::class ? 'mysql' : 'pgsql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'shop', 'username' => 'root', 'password' => ''];

    $driver = $driverClass === MySQLDriver::class
        ? new class($config) extends MySQLDriver
        {
            /** @var list<string> */
            public array $fixtures = [];

            public function explain(string $sql, array $bindings = []): ExplainOutput
            {
                return queryPlanFixture(array_shift($this->fixtures))['output'];
            }
        }
    : new class($config) extends PostgresSQLDriver
    {
        /** @var list<string> */
        public array $fixtures = [];

        public function explain(string $sql, array $bindings = []): ExplainOutput
        {
            return queryPlanFixture(array_shift($this->fixtures))['output'];
        }
    };

    $driver->fixtures = $fixtures;

    return $driver;
}

it('summarizes a grouped full scan for the agent without the full plan', function () {
    $project = Project::factory()->create();
    $driver = driverExplainingFixtures(MySQLDriver::class, 'mysql-group-full-scan');

    $plan = app(QueryPlanManager::class)->explain($driver, $project->id, null, queryPlanFixture('mysql-group-full-scan')['query'], ['days' => 7]);
    $summary = app(QueryPlanManager::class)->summarize($plan);

    expect($summary)->toMatchArray([
        'success' => true,
        'planId' => $plan->id,
        'driver' => 'mysql',
        'totalCost' => 6059.25,
        'steps' => [
            "1. Parcours complet de product_views : ~60\u{202F}030 lignes lues, ~20\u{202F}008 gardées par le filtre viewed_at >= (now() - interval 7 day) (100 % du coût)",
            '2. Regroupement par product_id dans une table temporaire',
            '3. Résultat : 1 ligne par product_id',
        ],
        'alerts' => [
            ['code' => 'FULL_SCAN', 'severity' => 'danger', 'table' => 'product_views', 'columns' => ['viewed_at'], 'message' => 'Parcours complet de product_views pour filtrer sur viewed_at'],
            ['code' => 'TEMP_TABLE', 'severity' => 'warn', 'table' => 'product_views', 'columns' => ['product_id'], 'message' => 'Table temporaire pour GROUP BY product_id'],
        ],
        'comparison' => null,
    ])->and($summary)->not->toHaveKeys(['nodes', 'raw'])
        ->and($summary['instructions'])->toContain('sans les créer toi-même')->toContain('colonnes du GROUP BY sont en tête');
});

it('compares the second plan of the same query in the conversation and keeps a remaining alert flagged', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversationId = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id])->id;
    $driver = driverExplainingFixtures(MySQLDriver::class, 'mysql-group-full-scan', 'mysql-group-range-scan');
    $query = queryPlanFixture('mysql-group-full-scan')['query'];
    $first = app(QueryPlanManager::class)->explain($driver, $project->id, $conversationId, $query, ['days' => 7]);

    $second = app(QueryPlanManager::class)->explain($driver, $project->id, $conversationId, $query, ['days' => 7]);
    $summary = app(QueryPlanManager::class)->summarize($second);

    expect($summary['comparison'])->toBe([
        'previousPlanId' => $first->id,
        'costBefore' => 6059.25,
        'costAfter' => 1694.6,
        'costChange' => '-72 %',
        'rowsReadBefore' => 60030,
        'rowsReadAfter' => 8412,
        'resolvedAlerts' => ['FULL_SCAN sur product_views'],
        'remainingAlerts' => ['TEMP_TABLE sur product_views'],
        'newAlerts' => [],
    ])->and(array_column($summary['alerts'], 'status', 'code'))->toBe(['TEMP_TABLE' => 'remaining'])
        ->and($summary['instructions'])->toContain('Ne prétends pas qu\'une alerte restante a disparu');
    expect($second->payload['previous']['planId'])->toBe($first->id)
        ->and($second->payload['nodes'][2]['before'])->toMatchArray(['label' => 'Parcours complet', 'rowsExamined' => 60030, 'cost' => 6059.25]);
});

it('summarizes a join of PostgreSQL leaves to root, without the hash step', function () {
    $project = Project::factory()->create();
    $driver = driverExplainingFixtures(PostgresSQLDriver::class, 'postgres-join');

    $plan = app(QueryPlanManager::class)->explain($driver, $project->id, null, queryPlanFixture('postgres-join')['query'], []);
    $summary = app(QueryPlanManager::class)->summarize($plan);

    expect($summary['steps'])->toBe([
        "1. Parcours complet de orders : ~60\u{202F}000 lignes lues, ~21\u{202F}400 gardées par le filtre (status)::text = 'paid'::text (55 % du coût)",
        "2. Parcours complet de customers (~5\u{202F}200 lignes) pour construire une table de hachage (4 % du coût, normal)",
        '3. Jointure par hachage sur o.customer_id = c.id (14 % du coût)',
        "4. Tri de ~21\u{202F}400 lignes par o.created_at DESC (28 % du coût)",
        '5. Limite aux 20 premières lignes',
    ])->and(array_map(fn (array $alert): array => [$alert['code'], $alert['table'], $alert['columns']], $summary['alerts']))->toBe([
        ['FULL_SCAN', 'orders', ['status']],
        ['SORT', 'orders', ['created_at']],
    ]);
});
