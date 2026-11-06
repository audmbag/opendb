<?php

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('table-changes-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, function (Builder $schema) {
        shopDatabase($schema);

        $schema->table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->integer('age')->default(0);
        });

        $schema->create('events', function (Blueprint $table) {
            $table->string('name');
        });
    });
});

afterEach(function () {
    @unlink($this->databasePath);
});

function shopConnection(): Connection
{
    return test()->project->driver_config->getDriver()->getConnection();
}

/**
 * @param  list<array<string, mixed>>  $changes
 */
function postTableChanges(string $table, array $changes, string $uri = 'changes')
{
    return test()->actingAs(test()->user)->postJson('/'.test()->project->id."/tables/{$table}/{$uri}", ['changes' => $changes]);
}

it('applies the updates, inserts and deletes together', function () {
    $response = postTableChanges('customers', [
        ['type' => 'update', 'key' => ['id' => 1], 'values' => ['name' => 'Ada Lovelace', 'age' => '36']],
        ['type' => 'insert', 'values' => ['name' => 'Linus', 'city' => 'Helsinki']],
        ['type' => 'delete', 'key' => ['id' => 3]],
    ]);

    $response->assertOk()->assertExactJson(['data' => ['inserted' => 1, 'updated' => 1, 'deleted' => 1]]);
    expect(shopConnection()->table('customers')->orderBy('id')->get(['id', 'name', 'city', 'age'])->map(fn ($row) => (array) $row)->all())->toBe([
        ['id' => 1, 'name' => 'Ada Lovelace', 'city' => 'Paris', 'age' => 36],
        ['id' => 2, 'name' => 'Grace', 'city' => 'Lyon', 'age' => 0],
        ['id' => 4, 'name' => 'Linus', 'city' => 'Helsinki', 'age' => 0],
    ]);
});

it('keeps none of the changes when one of them fails', function () {
    $response = postTableChanges('customers', [
        ['type' => 'update', 'key' => ['id' => 1], 'values' => ['name' => 'Ada Lovelace']],
        ['type' => 'update', 'key' => ['id' => 99], 'values' => ['name' => 'Nobody']],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('changes.1');
    expect(shopConnection()->table('customers')->where('id', 1)->value('name'))->toBe('Ada');
});

it('points at the values that do not fit their column', function () {
    $response = postTableChanges('customers', [
        ['type' => 'update', 'key' => ['id' => 1], 'values' => ['name' => 'Ada Lovelace']],
        ['type' => 'update', 'key' => ['id' => 2], 'values' => ['age' => 'old', 'email' => 'ada@example.com']],
        ['type' => 'insert', 'values' => ['city' => 'Rome']],
    ]);

    $response->assertUnprocessable()->assertOnlyJsonValidationErrors(['changes.1.values.age', 'changes.2.values.name']);
    expect(shopConnection()->table('customers')->where('id', 1)->value('name'))->toBe('Ada');
});

it('refuses a value already used by another row of a unique column, but not the value the row already has', function () {
    shopConnection()->table('customers')->where('id', 1)->update(['email' => 'ada@example.com']);

    postTableChanges('customers', [['type' => 'update', 'key' => ['id' => 2], 'values' => ['email' => 'ada@example.com']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('changes.0.values.email');

    postTableChanges('customers', [['type' => 'update', 'key' => ['id' => 1], 'values' => ['email' => 'ada@example.com', 'name' => 'Ada L.']]])
        ->assertOk();
});

it('keeps an empty string apart from NULL, and spaces as typed', function () {
    postTableChanges('customers', [
        ['type' => 'update', 'key' => ['id' => 1], 'values' => ['name' => '', 'city' => null]],
        ['type' => 'update', 'key' => ['id' => 2], 'values' => ['name' => '  Grace  ']],
    ])->assertOk();

    expect(shopConnection()->table('customers')->where('id', 1)->first(['name', 'city']))->toEqual((object) ['name' => '', 'city' => null])
        ->and(shopConnection()->table('customers')->where('id', 2)->value('name'))->toBe('  Grace  ');
});

it('refuses to change the primary key or a column the table does not have', function () {
    postTableChanges('customers', [['type' => 'update', 'key' => ['id' => 1], 'values' => ['id' => 10, 'nickname' => 'Ada']]])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['changes.0.values.id', 'changes.0.values.nickname']);
});

it('refuses to update or delete a row designated by something else than its primary key', function () {
    postTableChanges('customers', [['type' => 'delete', 'key' => ['name' => 'Ada']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('changes.0');

    expect(shopConnection()->table('customers')->count())->toBe(3);
});

it('only inserts rows in a table without primary key', function () {
    postTableChanges('events', [['type' => 'insert', 'values' => ['name' => 'launch']]])->assertOk();

    postTableChanges('events', [['type' => 'update', 'key' => ['name' => 'launch'], 'values' => ['name' => 'landing']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('changes.0');

    expect(shopConnection()->table('events')->pluck('name')->all())->toBe(['launch']);
});

it('shows the statement of each change without applying it', function () {
    $response = postTableChanges('customers', [
        ['type' => 'update', 'key' => ['id' => 1], 'values' => ['name' => "Ada O'Brien"]],
        ['type' => 'insert', 'values' => ['name' => 'Linus']],
        ['type' => 'delete', 'key' => ['id' => 3]],
    ], 'changes/preview');

    $response->assertOk()->assertExactJson(['data' => ['statements' => [
        'update "customers" set "name" = \'Ada O\'\'Brien\' where "id" = 1;',
        'insert into "customers" ("name") values (\'Linus\');',
        'delete from "customers" where "id" = 3;',
    ]]]);
    expect(shopConnection()->table('customers')->count())->toBe(3);
});

it('keeps the changes of a project away from other users', function () {
    $this->actingAs(User::factory()->create())
        ->postJson("/{$this->project->id}/tables/customers/changes", ['changes' => [['type' => 'delete', 'key' => ['id' => 1]]]])
        ->assertForbidden();

    expect(shopConnection()->table('customers')->count())->toBe(3);
});
