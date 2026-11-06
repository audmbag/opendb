<?php

use App\Ai\Tools\Database\CreateRecord;
use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ReadQuery;
use App\Ai\Tools\Database\ShowKpis;
use App\Ai\Tools\Database\ShowQueryResults;
use App\Ai\Tools\Database\ShowRecord;
use App\Ai\Tools\Database\ShowSchemaDiagram;
use App\Ai\Tools\Database\WriteQuery;
use App\Models\User;
use App\Services\QueryPlan\QueryPlanManager;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('hidden-tables-').'.sqlite';
    $this->project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...));
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);
    $this->driver = $this->project->driver_config->getAgentDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('leaves the disabled tables out of the schema given to the agent', function () {
    $schema = json_decode((string) new DatabaseSchema($this->driver)->handle(new Request([])), true);
    $orders = json_decode((string) new FocusTable($this->driver)->handle(new Request(['tableName' => 'orders', 'withRelatedTables' => true])), true);

    expect(json_decode((string) new ListTables($this->driver)->handle(new Request([])), true))->toBe(['orders: 4 columns, has PK, 0 FKs'])
        ->and(array_column($schema, 'name'))->toBe(['orders'])
        ->and(array_column($orders, 'name'))->toBe(['orders'])
        ->and($orders[0]['foreign_keys'])->toBe([]);
});

it('keeps the disabled tables visible outside of the agent', function () {
    expect($this->project->driver_config->getDriver()->listTables(false))->toBe(['customers', 'orders']);
});

it('tells the agent that a disabled table may not be used', function (Closure $call) {
    expect($call($this->driver))->toContain("La table [customers] est désactivée pour l'agent");
})->with([
    'focus' => [fn ($driver) => (string) new FocusTable($driver)->handle(new Request(['tableName' => 'customers']))],
    'diagram' => [fn ($driver) => (string) new ShowSchemaDiagram($driver)->handle(new Request(['focusedTable' => ['tableName' => 'customers', 'withRelatedTables' => false]]))],
    'profile' => [fn ($driver) => (string) new ProfileTable($driver)->handle(new Request(['tableName' => 'customers']))],
    'record' => [fn ($driver) => (string) new ShowRecord($driver)->handle(new Request(['tableName' => 'customers', 'key' => ['id' => 1]]))],
]);

it('rejects the queries of the agent citing a disabled table', function (Closure $call) {
    expect($call($this->driver))->toContain('elle cite la table [customers]');
})->with([
    'read' => [fn ($driver) => (string) new ReadQuery($driver)->handle(new Request(['query' => 'SELECT name FROM customers', 'bindings' => []]))],
    'results' => [fn ($driver) => (string) new ShowQueryResults($driver)->handle(new Request(['query' => 'SELECT o.id FROM orders o JOIN customers c ON c.id = o.customer_id', 'bindings' => []]))],
    'kpis' => [fn ($driver) => (string) new ShowKpis($driver)->handle(new Request(['kpis' => [['label' => 'Clients', 'query' => 'SELECT COUNT(*) FROM customers', 'bindings' => [], 'format' => 'number']]]))],
    'explain' => [fn ($driver) => (string) new ExplainQuery($driver, app(QueryPlanManager::class), test()->project->id)->handle(new Request(['query' => 'SELECT * FROM customers', 'bindings' => []]))],
]);

it('lets the agent query the other tables', function () {
    $result = (string) new ReadQuery($this->driver)->handle(new Request(['query' => 'SELECT COUNT(*) AS total FROM orders', 'bindings' => []]));

    expect(json_decode($result, true))->toBe([['total' => 3]]);
});

it('rejects a change of a disabled table without asking to approve it', function () {
    $tool = new WriteQuery($this->driver);
    $request = new Request(['query' => 'DELETE FROM customers', 'bindings' => []]);

    expect($tool->shouldRequestApproval($request))->toBeNull()
        ->and((string) $tool->handle($request))->toContain('elle cite la table [customers]')
        ->and($this->driver->getConnection()->table('customers')->count())->toBe(3);
});

it('refuses to create a record in a disabled table', function () {
    Cache::put(CreateRecord::submissionCacheKey('call_1'), ['table' => 'customers', 'values' => ['name' => 'Linus']], now()->addHour());

    $result = (string) new CreateRecord($this->driver)->handle(new Request(['table' => 'customers'], 'call_1'));

    expect($result)->toContain("La table [customers] est désactivée pour l'agent")
        ->and($this->driver->getConnection()->table('customers')->count())->toBe(3);
});
