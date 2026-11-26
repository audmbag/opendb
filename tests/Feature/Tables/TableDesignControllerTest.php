<?php

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
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

it('adds a foreign key to an existing table from the structure it reads, keeping its rows', function () {
    designConnection()->getSchemaBuilder()->create('reviews', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('customer_id');
        $table->text('body');
    });
    designConnection()->table('reviews')->insert(['customer_id' => 2, 'body' => 'Parfait']);

    $design = $this->actingAs($this->user)->getJson(tablesUri('/reviews/design'))->json('data');
    $design['foreignKeys'][] = [
        'original' => null, 'name' => null, 'columns' => ['customer_id'], 'referencesTable' => 'customers',
        'referencesColumns' => ['id'], 'onDelete' => 'cascade', 'onUpdate' => null,
    ];

    $this->actingAs($this->user)->putJson(tablesUri('/reviews/design'), $design)->assertOk();

    $connection = designConnection();
    expect($connection->getSchemaBuilder()->getForeignKeys('reviews'))->sequence(
        fn ($foreignKey) => $foreignKey->columns->toBe(['customer_id'])
            ->foreign_table->toBe('customers')
            ->foreign_columns->toBe(['id'])
            ->on_delete->toBe('cascade'),
    )->and($connection->table('reviews')->pluck('body')->all())->toBe(['Parfait']);
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

it('moves a column of a SQLite table, keeping its rows, constraints, indexes, triggers, views and counter', function () {
    $connection = designConnection();
    $connection->statement(<<<'SQL'
        create table "notes" (
            "id" integer primary key autoincrement not null,
            "title, with comma" varchar not null default 'a, (b)', -- a comment, with a comma
            body text check (length(body) > 0) collate nocase,
            [score] integer default (1 + 2),
            words integer generated always as (length(body)) virtual,
            constraint notes_title_unique unique ("title, with comma")
        )
        SQL);
    $connection->statement('create index notes_score_index on notes (score)');
    $connection->statement('create view long_notes as select id, body from notes where words > 3');
    $connection->statement('create trigger notes_score_trigger after insert on notes begin update notes set score = score + 1 where id = new.id; end');
    $connection->table('notes')->insert([['title, with comma' => 'One', 'body' => 'first'], ['title, with comma' => 'Two', 'body' => 'hi'], ['title, with comma' => 'Three', 'body' => 'last']]);
    $connection->table('notes')->where('id', 3)->delete();

    $move = ['column' => 'score', 'after' => 'id'];

    $this->actingAs($this->user)->postJson(tablesUri('/notes/columns/move/preview'), $move)
        ->assertOk()
        ->assertJsonPath('data.statements', fn (array $statements) => str_starts_with($statements[1], 'create table "__temp__notes"'));
    expect($connection->getSchemaBuilder()->getColumnListing('notes'))->toBe(['id', 'title, with comma', 'body', 'score', 'words']);

    $this->actingAs($this->user)->postJson(tablesUri('/notes/columns/move'), $move)->assertOk();

    expect($connection->getSchemaBuilder()->getColumnListing('notes'))->toBe(['id', 'score', 'title, with comma', 'body', 'words'])
        ->and($connection->table('notes')->get(['id', 'score', 'body', 'words'])->map(fn ($row) => (array) $row)->all())->toBe([
            ['id' => 1, 'score' => 4, 'body' => 'first', 'words' => 5],
            ['id' => 2, 'score' => 4, 'body' => 'hi', 'words' => 2],
        ])
        ->and($connection->table('long_notes')->pluck('id')->all())->toBe([1])
        ->and($connection->scalar("select sql from sqlite_master where name = 'notes'"))->toContain('check (length(body) > 0) collate nocase', 'a comment, with a comma')
        ->and($connection->getSchemaBuilder()->getIndexListing('notes'))->toContain('notes_score_index')
        ->and(fn () => $connection->table('notes')->insert(['title, with comma' => 'One', 'body' => 'again']))->toThrow(Exception::class)
        ->and(fn () => $connection->table('notes')->insert(['title, with comma' => 'Empty', 'body' => '']))->toThrow(Exception::class);

    $connection->table('notes')->insert(['title, with comma' => 'Four', 'body' => 'next']);
    expect($connection->table('notes')->where('body', 'next')->first())->id->toBe(4)->score->toBe(4);
});

it('moves a column first, keeping the foreign keys from and to the table', function () {
    $connection = designConnection();

    $this->actingAs($this->user)->postJson(tablesUri('/orders/columns/move'), ['column' => 'status', 'after' => null])->assertOk();
    $this->actingAs($this->user)->postJson(tablesUri('/customers/columns/move'), ['column' => 'id', 'after' => 'city'])->assertOk();

    expect($connection->getSchemaBuilder()->getColumnListing('orders'))->toBe(['status', 'id', 'customer_id', 'total'])
        ->and($connection->getSchemaBuilder()->getColumnListing('customers'))->toBe(['name', 'city', 'id'])
        ->and($connection->getSchemaBuilder()->getForeignKeys('orders'))->sequence(fn ($foreignKey) => $foreignKey->foreign_table->toBe('customers'))
        ->and($connection->select('pragma foreign_key_check'))->toBe([])
        ->and((int) $connection->scalar('pragma foreign_keys'))->toBe(1)
        ->and($connection->table('orders')->where('customer_id', 1)->sum('total'))->toBe(200);
});

it('runs nothing when the column is already in place', function () {
    $this->actingAs($this->user)->postJson(tablesUri('/customers/columns/move'), ['column' => 'name', 'after' => 'id'])
        ->assertOk()
        ->assertJsonPath('data.statements', []);
});

it('refuses to move a column the table does not have', function () {
    $this->actingAs($this->user)->postJson(tablesUri('/customers/columns/move/preview'), ['column' => 'email', 'after' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('design');

    $this->actingAs($this->user)->postJson(tablesUri('/customers/columns/move'), ['column' => 'name'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('after');
});

it('puts the columns in the order of the design, new ones included, keeping the rows', function () {
    $design = $this->actingAs($this->user)->getJson(tablesUri('/customers/design'))->json('data');
    [$id, $name, $city] = $design['columns'];
    $design['columns'] = [$id, $city, designColumn('email', 'string', ['nullable' => true]), [...$name, 'name' => 'full_name']];

    $this->actingAs($this->user)->postJson(tablesUri('/customers/design/preview'), $design)->assertOk();
    expect(designConnection()->getSchemaBuilder()->getColumnListing('customers'))->toBe(['id', 'name', 'city']);

    $this->actingAs($this->user)->putJson(tablesUri('/customers/design'), $design)->assertOk();

    $connection = designConnection();
    expect($connection->getSchemaBuilder()->getColumnListing('customers'))->toBe(['id', 'city', 'email', 'full_name'])
        ->and($connection->table('customers')->pluck('full_name', 'city')->all())->toBe(['Paris' => 'Ada', 'Lyon' => 'Grace', '' => 'Alan'])
        ->and($connection->getSchemaBuilder()->getForeignKeys('orders'))->toHaveCount(1);
});
