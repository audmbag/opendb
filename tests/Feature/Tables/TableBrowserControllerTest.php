<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('table-browser-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * The names of the customers returned by the rows endpoint for the given view.
 *
 * @param  array<string, mixed>  $view
 * @return list<string>
 */
function browsedCustomerNames(array $view = []): array
{
    $response = test()->actingAs(test()->user)->getJson('/'.test()->project->id.'/tables/customers/rows?'.http_build_query($view));

    $response->assertOk();

    return array_column($response->json('data.rows'), 'name');
}

it('opens the Tables tab with the tables of the database, and the structure of the opened table once deferred', function () {
    $this->actingAs($this->user)
        ->get("/{$this->project->id}/tables/orders")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/tables/index')
            ->where('table', 'orders')
            ->where('tables', [
                ['name' => 'customers', 'columns' => 3, 'hasPrimaryKey' => true, 'foreignKeys' => 0, 'agentAccessEnabled' => true],
                ['name' => 'orders', 'columns' => 4, 'hasPrimaryKey' => true, 'foreignKeys' => 1, 'agentAccessEnabled' => true],
            ])
            ->missing('structure')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('structure.primaryKey', ['id'])
                ->where('structure.columns.1.name', 'customer_id')
                ->where('structure.columns.1.references', ['table' => 'customers', 'column' => 'id'])
            )
        );
});

it('marks the tables the agent may not read', function () {
    $this->project->driver_config->update(['disabled_tables' => ['orders']]);

    $this->actingAs($this->user)
        ->get("/{$this->project->id}/tables")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('table', null)
            ->where('tables.0.agentAccessEnabled', true)
            ->where('tables.1.agentAccessEnabled', false)
        );
});

it('returns 404 when the opened table is not in the database', function () {
    $this->actingAs($this->user)->get("/{$this->project->id}/tables/invoices")->assertNotFound();
});

it('keeps the tables of a project away from other users', function (string $uri) {
    $this->actingAs(User::factory()->create())->getJson("/{$this->project->id}{$uri}")->assertForbidden();
})->with([
    'page' => '/tables/customers',
    'rows' => '/tables/customers/rows',
    'export' => '/tables/customers/export',
]);

it('sends guests to the login page', function () {
    $this->get("/{$this->project->id}/tables")->assertRedirect('/login');
});

it('reads one page of rows in the order of the primary key, telling whether another page follows', function () {
    $response = $this->actingAs($this->user)->getJson("/{$this->project->id}/tables/customers/rows?perPage=50");

    $response->assertOk()->assertExactJson(['data' => [
        'rows' => [
            ['id' => 1, 'name' => 'Ada', 'city' => 'Paris'],
            ['id' => 2, 'name' => 'Grace', 'city' => 'Lyon'],
            ['id' => 3, 'name' => 'Alan', 'city' => null],
        ],
        'page' => 1,
        'perPage' => 50,
        'hasMore' => false,
        'sql' => 'select * from "customers" order by "id" asc limit 50 offset 0',
    ]]);
});

it('tells that another page follows when the table has more rows than the page', function () {
    $this->project->driver_config->getDriver()->getConnection()->table('customers')->insert(
        array_map(fn (int $index) => ['name' => "Client {$index}"], range(1, 50)),
    );

    $response = $this->actingAs($this->user)->getJson("/{$this->project->id}/tables/customers/rows?perPage=50&page=1");

    $response->assertOk()->assertJsonCount(50, 'data.rows')->assertJsonPath('data.hasMore', true);

    $this->actingAs($this->user)->getJson("/{$this->project->id}/tables/customers/rows?perPage=50&page=2")
        ->assertOk()
        ->assertJsonCount(3, 'data.rows')
        ->assertJsonPath('data.rows.0.name', 'Client 48')
        ->assertJsonPath('data.hasMore', false);
});

it('sorts the rows on several columns', function () {
    expect(browsedCustomerNames(['sorts' => [['column' => 'city', 'direction' => 'desc'], ['column' => 'name']]]))
        ->toBe(['Ada', 'Grace', 'Alan']);
});

it('filters the rows with each operator', function (string $column, string $operator, mixed $value, array $names) {
    expect(browsedCustomerNames(['filters' => [['column' => $column, 'operator' => $operator, 'value' => $value]]]))->toBe($names);
})->with([
    'equals' => ['name', '=', 'Ada', ['Ada']],
    'not equals' => ['name', '!=', 'Ada', ['Grace', 'Alan']],
    'greater than' => ['id', '>', 2, ['Alan']],
    'greater than or equals' => ['id', '>=', 2, ['Grace', 'Alan']],
    'less than' => ['id', '<', 2, ['Ada']],
    'less than or equals' => ['id', '<=', 2, ['Ada', 'Grace']],
    'contains, ignoring case' => ['name', 'contains', 'A', ['Ada', 'Grace', 'Alan']],
    'not contains' => ['name', 'not_contains', 'la', ['Ada', 'Grace']],
    'starts with' => ['name', 'starts_with', 'al', ['Alan']],
    'ends with' => ['city', 'ends_with', 'is', ['Ada']],
    'in a comma separated list' => ['city', 'in', 'Lyon, Paris', ['Ada', 'Grace']],
    'is null' => ['city', 'is_null', null, ['Alan']],
    'is not null' => ['city', 'is_not_null', null, ['Ada', 'Grace']],
]);

it('keeps the rows matching every filter, or any of them', function (string $match, array $names) {
    expect(browsedCustomerNames(['match' => $match, 'filters' => [
        ['column' => 'name', 'operator' => 'starts_with', 'value' => 'A'],
        ['column' => 'city', 'operator' => '=', 'value' => 'Lyon'],
    ]]))->toBe($names);
})->with([
    'all' => ['all', []],
    'any' => ['any', ['Ada', 'Grace', 'Alan']],
]);

it('refuses a view that would write its own SQL', function (array $view, string $error) {
    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/customers/rows?".http_build_query($view))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'sort on an unknown column' => [['sorts' => [['column' => 'name; DROP TABLE customers', 'direction' => 'asc']]], 'query'],
    'filter on an unknown column' => [['filters' => [['column' => '1=1) OR (1', 'operator' => '=', 'value' => 'x']]], 'query'],
    'unknown operator' => [['filters' => [['column' => 'name', 'operator' => '= 1 OR 1 =', 'value' => '1']]], 'filters.0.operator'],
    'unknown direction' => [['sorts' => [['column' => 'name', 'direction' => 'desc; --']]], 'sorts.0.direction'],
    'filter without value' => [['filters' => [['column' => 'name', 'operator' => '=']]], 'filters.0.value'],
    'page size outside the list' => [['perPage' => 100000], 'perPage'],
]);

it('replaces binary values by their size', function () {
    $connection = $this->project->driver_config->getDriver()->getConnection();
    $connection->getSchemaBuilder()->create('files', function (Blueprint $table) {
        $table->id();
        $table->binary('content');
    });
    $connection->table('files')->insert(['content' => "\xFF\xD8\xFF\xE0"]);

    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/files/rows")
        ->assertOk()
        ->assertJsonPath('data.rows.0.content', '[binaire, 4 octets]');
});

it('counts the rows matching the filters', function () {
    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/customers/count?".http_build_query(['filters' => [['column' => 'city', 'operator' => 'is_not_null']]]))
        ->assertOk()
        ->assertExactJson(['data' => ['total' => 2, 'estimated' => false]]);
});

it('describes the columns, the indexes and the foreign keys pointing to the table', function () {
    $response = $this->actingAs($this->user)->getJson("/{$this->project->id}/tables/customers/structure");

    $response->assertOk()
        ->assertJsonPath('data.primaryKey', ['id'])
        ->assertJsonPath('data.columns.0', [
            'name' => 'id', 'kind' => 'integer', 'db_type' => 'integer', 'nullable' => false, 'default' => null, 'primary' => true,
            'auto_increment' => true, 'generated' => false, 'editable' => true, 'max_length' => null, 'options' => null, 'unique' => false, 'references' => null,
        ])
        ->assertJsonPath('data.columns.2.nullable', true)
        ->assertJsonPath('data.incomingReferences', [['table' => 'orders', 'columns' => ['customer_id'], 'references_columns' => ['id']]]);
});

it('shows the statement that created the table', function () {
    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/customers/definition")
        ->assertOk()
        ->assertJsonPath('data.sql', fn (string $sql) => str_starts_with($sql, 'CREATE TABLE "customers"') && str_ends_with($sql, ');'));
});

it('opens a row with the rows it references and the rows referencing it', function () {
    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/customers/record?".http_build_query(['key' => ['id' => 1]]))
        ->assertOk()
        ->assertJsonPath('data.values.name', 'Ada')
        ->assertJsonPath('data.incoming.0.table', 'orders')
        ->assertJsonPath('data.incoming.0.count', 2);
});

it('exports every row of the filtered and sorted view', function (string $format, string $expected) {
    $response = $this->actingAs($this->user)->get("/{$this->project->id}/tables/customers/export?".http_build_query([
        'format' => $format,
        'filters' => [['column' => 'city', 'operator' => 'is_not_null']],
        'sorts' => [['column' => 'name', 'direction' => 'desc']],
    ]));

    $response->assertOk()->assertDownload("customers.{$format}");
    expect($response->streamedContent())->toBe($expected);
})->with([
    'csv' => ['csv', "id,name,city\n2,Grace,Lyon\n1,Ada,Paris\n"],
    'json' => ['json', "[\n  {\"id\":2,\"name\":\"Grace\",\"city\":\"Lyon\"},\n  {\"id\":1,\"name\":\"Ada\",\"city\":\"Paris\"}\n]"],
]);

it('reports a wrong filter instead of downloading an empty export', function () {
    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/customers/export?".http_build_query(['filters' => [['column' => 'unknown', 'operator' => 'is_null']]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('query');
});
