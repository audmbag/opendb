<?php

use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Ai\Tools\Erd\ProposeSchemaChanges;
use App\Models\User;
use Illuminate\Database\Schema\Builder;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('schema-changes-').'.sqlite';
    $this->project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...));
    $this->driver = $this->project->driver_config->getAgentDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  list<array<string, mixed>>  $changes
 * @return array<string, mixed>
 */
function proposeSchemaChanges(array $changes, SchemaChangeApplyModeEnum $mode = SchemaChangeApplyModeEnum::Auto): array
{
    return json_decode((string) new ProposeSchemaChanges(test()->driver, $mode)->handle(new Request(['changes' => $changes])), true);
}

function shopSchema(): Builder
{
    return test()->driver->getConnection()->getSchemaBuilder();
}

it('creates tables referencing each other in the order their keys need', function () {
    $result = proposeSchemaChanges([
        ['action' => 'create_table', 'table' => 'invoice_lines', 'columns' => [
            ['name' => 'id', 'type' => 'bigInteger', 'autoIncrement' => true, 'unsigned' => true],
            ['name' => 'invoice_id', 'type' => 'bigInteger', 'unsigned' => true],
            ['name' => 'amount', 'type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0],
        ], 'foreignKeys' => [['columns' => ['invoice_id'], 'referencesTable' => 'invoices', 'referencesColumns' => ['id'], 'onDelete' => 'cascade']]],
        ['action' => 'create_table', 'table' => 'invoices', 'columns' => [
            ['name' => 'id', 'type' => 'bigInteger', 'autoIncrement' => true, 'unsigned' => true],
            ['name' => 'customer_id', 'type' => 'bigInteger', 'unsigned' => true],
        ], 'foreignKeys' => [['columns' => ['customer_id'], 'referencesTable' => 'customers', 'referencesColumns' => ['id']]]],
    ]);

    expect($result)->success->toBeTrue()
        ->applied->toBe([['kind' => 'create_table', 'table' => 'invoices'], ['kind' => 'create_table', 'table' => 'invoice_lines']])
        ->and(collect(shopSchema()->getForeignKeys('invoice_lines'))->pluck('foreign_table')->all())->toBe(['invoices'])
        ->and(shopSchema()->getColumnListing('invoices'))->toBe(['id', 'customer_id']);
});

it('creates tables referencing each other in a cycle, adding the last keys once both exist', function () {
    $result = proposeSchemaChanges([
        ['action' => 'create_table', 'table' => 'teams', 'columns' => [
            ['name' => 'id', 'type' => 'integer', 'autoIncrement' => true],
            ['name' => 'captain_id', 'type' => 'integer', 'nullable' => true],
        ], 'foreignKeys' => [['columns' => ['captain_id'], 'referencesTable' => 'players', 'referencesColumns' => ['id']]]],
        ['action' => 'create_table', 'table' => 'players', 'columns' => [
            ['name' => 'id', 'type' => 'integer', 'autoIncrement' => true],
            ['name' => 'team_id', 'type' => 'integer'],
        ], 'foreignKeys' => [['columns' => ['team_id'], 'referencesTable' => 'teams', 'referencesColumns' => ['id']]]],
    ]);

    expect($result['success'])->toBeTrue()
        ->and(collect(shopSchema()->getForeignKeys('teams'))->pluck('foreign_table')->all())->toBe(['players'])
        ->and(collect(shopSchema()->getForeignKeys('players'))->pluck('foreign_table')->all())->toBe(['teams']);
});

it('changes a table from its structure in the database and keeps its rows', function () {
    $result = proposeSchemaChanges([
        ['action' => 'create_table', 'table' => 'regions', 'columns' => [['name' => 'id', 'type' => 'integer', 'autoIncrement' => true]]],
        ['action' => 'alter_table', 'table' => 'customers',
            'addColumns' => [['name' => 'region_id', 'type' => 'integer', 'nullable' => true]],
            'changeColumns' => [['name' => 'city', 'newName' => 'town']],
            'dropColumns' => ['name'],
            'addForeignKeys' => [['columns' => ['region_id'], 'referencesTable' => 'regions', 'referencesColumns' => ['id']]]],
    ]);

    expect($result['success'])->toBeTrue()
        ->and(shopSchema()->getColumnListing('customers'))->toBe(['id', 'town', 'region_id'])
        ->and($this->driver->getConnection()->table('customers')->pluck('town')->all())->toBe(['Paris', 'Lyon', null])
        ->and(collect(shopSchema()->getForeignKeys('customers'))->pluck('foreign_table')->all())->toBe(['regions']);
});

it('renames and drops tables', function () {
    $result = proposeSchemaChanges([
        ['action' => 'drop_table', 'table' => 'orders'],
        ['action' => 'alter_table', 'table' => 'customers', 'rename' => 'clients'],
    ]);

    expect($result['success'])->toBeTrue()
        ->and(shopSchema()->hasTable('orders'))->toBeFalse()
        ->and(shopSchema()->hasTable('clients'))->toBeTrue()
        ->and(shopSchema()->hasTable('customers'))->toBeFalse();
});

it('runs another statement of structure as it is', function () {
    $result = proposeSchemaChanges([
        ['action' => 'raw_sql', 'statement' => "CREATE VIEW paid_orders AS SELECT * FROM orders WHERE status = 'paid';"],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($this->driver->getConnection()->table('paid_orders')->count())->toBe(2);
});

it('runs nothing and names what is wrong when a change cannot be made', function (array $change, string $error) {
    $result = proposeSchemaChanges([
        ['action' => 'create_table', 'table' => 'notes', 'columns' => [['name' => 'id', 'type' => 'integer', 'autoIncrement' => true]]],
        $change,
    ]);

    expect($result['success'])->toBeFalse()
        ->and(implode("\n", $result['errors']))->toContain($error)
        ->and(shopSchema()->hasTable('notes'))->toBeFalse();
})->with([
    'unknown type' => [['action' => 'create_table', 'table' => 'tags', 'columns' => [['name' => 'label', 'type' => 'varchar']]], 'columns.0.type'],
    'existing table' => [['action' => 'create_table', 'table' => 'customers', 'columns' => [['name' => 'id', 'type' => 'integer']]], 'La table customers existe déjà.'],
    'missing table' => [['action' => 'alter_table', 'table' => 'invoices', 'dropColumns' => ['id']], 'La table invoices n’existe pas.'],
    'missing column' => [['action' => 'alter_table', 'table' => 'orders', 'dropColumns' => ['paid_at']], 'La colonne paid_at n’existe pas dans la table orders.'],
    'missing reference' => [['action' => 'alter_table', 'table' => 'orders', 'addColumns' => [['name' => 'shop_id', 'type' => 'integer', 'nullable' => true]], 'addForeignKeys' => [['columns' => ['shop_id'], 'referencesTable' => 'shops', 'referencesColumns' => ['id']]]], 'La table référencée shops n’existe pas.'],
    'missing referenced column' => [['action' => 'alter_table', 'table' => 'orders', 'addColumns' => [['name' => 'buyer_code', 'type' => 'string', 'nullable' => true]], 'addForeignKeys' => [['columns' => ['buyer_code'], 'referencesTable' => 'customers', 'referencesColumns' => ['code']]]], 'La colonne référencée customers.code n’existe pas.'],
    'statement changing rows' => [['action' => 'raw_sql', 'statement' => 'DELETE FROM orders'], 'Seule une instruction de structure'],
    'administration statement' => [['action' => 'raw_sql', 'statement' => 'CREATE USER bob'], 'administrateur'],
    'table changed twice' => [['action' => 'drop_table', 'table' => 'notes'], 'La table notes est modifiée deux fois'],
]);

it('keeps the tables hidden from the agent out of reach', function () {
    $this->project->driver_config->update(['disabled_tables' => ['orders']]);
    $this->driver = $this->project->driver_config->refresh()->getAgentDriver();

    $result = proposeSchemaChanges([['action' => 'drop_table', 'table' => 'orders']]);

    expect($result['errors'])->toBe(['drop_table orders: La table orders est masquée à l’assistant.'])
        ->and(shopSchema()->hasTable('orders'))->toBeTrue();
});

it('reports the change the database refused and the changes that stay made before it', function () {
    $result = proposeSchemaChanges([
        ['action' => 'create_table', 'table' => 'notes', 'columns' => [['name' => 'id', 'type' => 'integer', 'autoIncrement' => true]]],
        ['action' => 'alter_table', 'table' => 'orders', 'addIndexes' => [['columns' => ['customer_id'], 'unique' => true]]],
    ]);

    expect($result)->success->toBeFalse()
        ->statement->toContain('create unique index')
        ->applied->toBe([['kind' => 'create_table', 'table' => 'notes']]);
});

it('asks for an approval as the apply mode of the user says', function (SchemaChangeApplyModeEnum $mode, array $change, bool $asks) {
    $tool = new ProposeSchemaChanges($this->driver, $mode);

    expect($tool->shouldRequestApproval(new Request(['changes' => [$change]])) !== null)->toBe($asks);
})->with([
    'draft, an addition' => [SchemaChangeApplyModeEnum::Draft, ['action' => 'alter_table', 'table' => 'orders', 'addColumns' => [['name' => 'note', 'type' => 'text', 'nullable' => true]]], true],
    'additions, an addition' => [SchemaChangeApplyModeEnum::Additions, ['action' => 'alter_table', 'table' => 'orders', 'addColumns' => [['name' => 'note', 'type' => 'text', 'nullable' => true]]], false],
    'additions, a column dropped' => [SchemaChangeApplyModeEnum::Additions, ['action' => 'alter_table', 'table' => 'orders', 'dropColumns' => ['status']], true],
    'additions, a column renamed' => [SchemaChangeApplyModeEnum::Additions, ['action' => 'alter_table', 'table' => 'orders', 'changeColumns' => [['name' => 'status', 'newName' => 'state']]], true],
    'additions, a table dropped' => [SchemaChangeApplyModeEnum::Additions, ['action' => 'drop_table', 'table' => 'orders'], true],
    'auto, a table dropped' => [SchemaChangeApplyModeEnum::Auto, ['action' => 'drop_table', 'table' => 'orders'], false],
    'draft, changes with errors' => [SchemaChangeApplyModeEnum::Draft, ['action' => 'drop_table', 'table' => 'invoices'], false],
]);
