<?php

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('table-design-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, fn (Builder $schema) => shopDatabase($schema));
});

afterEach(function () {
    @unlink($this->databasePath);
});

function designConnection(): Connection
{
    return test()->project->driver_config->getDriver()->getConnection();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function designColumn(string $name, string $type, array $overrides = []): array
{
    return [
        'original' => null, 'changed' => false, 'name' => $name, 'type' => $type, 'length' => null, 'precision' => null,
        'scale' => null, 'unsigned' => false, 'nullable' => false, 'defaultKind' => 'none', 'default' => null,
        'autoIncrement' => false, 'options' => [], ...$overrides,
    ];
}

function tablesUri(string $path = ''): string
{
    return '/'.test()->project->id.'/tables'.$path;
}

it('creates a table with its columns, keys and indexes', function () {
    $design = [
        'name' => 'products',
        'columns' => [
            designColumn('id', 'bigInteger', ['autoIncrement' => true, 'unsigned' => true]),
            designColumn('customer_id', 'bigInteger', ['unsigned' => true, 'nullable' => true]),
            designColumn('sku', 'string', ['length' => 32]),
            designColumn('price', 'decimal', ['precision' => 8, 'scale' => 2, 'defaultKind' => 'value', 'default' => '0']),
            designColumn('kind', 'enum', ['options' => ['book', 'game']]),
        ],
        'primaryKey' => ['id'],
        'indexes' => [['original' => null, 'name' => null, 'columns' => ['sku'], 'unique' => true]],
        'foreignKeys' => [['original' => null, 'name' => null, 'columns' => ['customer_id'], 'referencesTable' => 'customers', 'referencesColumns' => ['id'], 'onDelete' => 'set null', 'onUpdate' => null]],
    ];

    $this->actingAs($this->user)->postJson(tablesUri('/preview'), $design)
        ->assertOk()
        ->assertJsonPath('data.statements.0', fn (string $sql) => str_starts_with($sql, 'create table "products"'));
    expect(designConnection()->getSchemaBuilder()->hasTable('products'))->toBeFalse();

    $this->actingAs($this->user)->postJson(tablesUri(), $design)->assertOk();

    $connection = designConnection();
    $connection->table('products')->insert(['customer_id' => 1, 'sku' => 'A-1', 'kind' => 'book']);
    expect($connection->table('products')->first())->toMatchArray(['id' => 1, 'sku' => 'A-1', 'price' => 0])
        ->and(fn () => $connection->table('products')->insert(['sku' => 'A-1', 'kind' => 'game']))->toThrow(Exception::class);

    $this->actingAs($this->user)->getJson(tablesUri('/products/design'))
        ->assertOk()
        ->assertJsonPath('data.primaryKey', ['id'])
        ->assertJsonPath('data.columns.2.type', 'string')
        ->assertJsonPath('data.foreignKeys.0.referencesTable', 'customers')
        ->assertJsonPath('data.foreignKeys.0.onDelete', 'set null');
});

it('changes a table: adds, renames, changes and drops columns while keeping its rows', function () {
    $design = $this->actingAs($this->user)->getJson(tablesUri('/customers/design'))->json('data');

    $design['columns'] = [
        $design['columns'][0],
        [...$design['columns'][1], 'name' => 'full_name', 'changed' => true, 'length' => 120],
        designColumn('email', 'string', ['nullable' => true]),
    ];
    $design['indexes'] = [['original' => null, 'name' => 'customers_email_unique', 'columns' => ['email'], 'unique' => true]];

    $this->actingAs($this->user)->postJson(tablesUri('/customers/design/preview'), $design)->assertOk();
    expect(designConnection()->getSchemaBuilder()->getColumnListing('customers'))->toBe(['id', 'name', 'city']);

    $this->actingAs($this->user)->putJson(tablesUri('/customers/design'), $design)->assertOk();

    $connection = designConnection();
    expect($connection->getSchemaBuilder()->getColumnListing('customers'))->toBe(['id', 'full_name', 'email'])
        ->and($connection->table('customers')->pluck('full_name')->all())->toBe(['Ada', 'Grace', 'Alan'])
        ->and($connection->table('orders')->count())->toBe(3);
});

it('reports the statement the database refuses', function () {
    $design = $this->actingAs($this->user)->getJson(tablesUri('/orders/design'))->json('data');
    $design['indexes'][] = ['original' => null, 'name' => null, 'columns' => ['customer_id'], 'unique' => true];

    $this->actingAs($this->user)->putJson(tablesUri('/orders/design'), $design)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('design');
});

it('refuses a design with two columns of the same name or a name to quote', function () {
    $this->actingAs($this->user)->postJson(tablesUri(), [
        'name' => 'bad name',
        'columns' => [designColumn('a', 'integer'), designColumn('A', 'integer')],
        'primaryKey' => [],
        'indexes' => [],
        'foreignKeys' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'columns.1.name']);
});

it('renames, copies, empties and drops a table', function () {
    $this->actingAs($this->user)->postJson(tablesUri('/customers/duplicate'), ['name' => 'clients', 'withRows' => true])->assertOk();
    expect(designConnection()->table('clients')->count())->toBe(3);

    $this->actingAs($this->user)->postJson(tablesUri('/clients/rename'), ['name' => 'people'])->assertOk();
    expect(designConnection()->getSchemaBuilder()->hasTable('people'))->toBeTrue();

    $this->actingAs($this->user)->postJson(tablesUri('/people/truncate'))->assertOk()->assertJsonPath('data.deleted', 3);
    expect(designConnection()->table('people')->count())->toBe(0);

    $this->actingAs($this->user)->deleteJson(tablesUri('/people'))->assertOk();
    expect(designConnection()->getSchemaBuilder()->hasTable('people'))->toBeFalse();
});

it('adds a row filled in the form, checked against the columns', function () {
    $this->actingAs($this->user)->postJson(tablesUri('/customers/records'), ['name' => 'Linus', 'city' => ''])
        ->assertOk()
        ->assertJsonPath('data.values.id', 4);

    $this->actingAs($this->user)->postJson(tablesUri('/customers/records'), ['city' => 'Rome'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
