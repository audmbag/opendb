<?php

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('import-export-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, fn (Builder $schema) => shopDatabase($schema));
});

afterEach(function () {
    @unlink($this->databasePath);
});

function importConnection(): Connection
{
    return test()->project->driver_config->getDriver()->getConnection();
}

function databaseUri(string $path): string
{
    return '/'.test()->project->id.$path;
}

it('exports the chosen tables to a SQL file', function () {
    $response = $this->actingAs($this->user)->postJson(databaseUri('/database/export'), [
        'tables' => [['name' => 'customers', 'schema' => true, 'data' => true]],
        'format' => 'sql',
        'compression' => 'none',
    ]);

    $response->assertOk()->assertDownload();
    $sql = file_get_contents($response->baseResponse->getFile()->getPathname());
    expect($sql)->toContain('CREATE TABLE')->toContain('Grace')->not->toContain('"orders"');
});

it('explains why nothing can be exported', function () {
    $this->actingAs($this->user)->postJson(databaseUri('/database/export'), [
        'tables' => [['name' => 'nowhere']],
        'format' => 'sql',
        'compression' => 'zip',
    ])->assertUnprocessable()->assertJsonValidationErrors('export');
});

it('runs a SQL script typed in the form, all or nothing', function () {
    $this->actingAs($this->user)->postJson(databaseUri('/database/import'), [
        'sql' => "INSERT INTO customers (name) VALUES ('Linus; Torvalds');\n-- a comment;\nINSERT INTO customers (name) VALUES ('Ken');",
        'mode' => 'transaction',
    ])->assertOk()->assertJsonPath('data.executed', 2)->assertJsonPath('data.kept', true);

    $this->actingAs($this->user)->postJson(databaseUri('/database/import'), [
        'sql' => "INSERT INTO customers (name) VALUES ('Dennis'); INSERT INTO nowhere VALUES (1);",
        'mode' => 'transaction',
    ])->assertOk()->assertJsonPath('data.failed', 1)->assertJsonPath('data.kept', false);

    expect(importConnection()->table('customers')->pluck('name')->all())->toBe(['Ada', 'Grace', 'Alan', 'Linus; Torvalds', 'Ken']);
});

it('keeps running the script after a failure when asked to', function () {
    $file = UploadedFile::fake()->createWithContent('dump.sql', "INSERT INTO nowhere VALUES (1);\nINSERT INTO customers (name) VALUES ('Dennis');");

    $this->actingAs($this->user)->post(databaseUri('/database/import'), ['file' => $file, 'mode' => 'continue'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.executed', 1)
        ->assertJsonPath('data.failures.0.index', 1);

    expect(importConnection()->table('customers')->where('name', 'Dennis')->exists())->toBeTrue();
});

it('refuses the statements reserved to an administrator', function () {
    $this->actingAs($this->user)->postJson(databaseUri('/database/import'), ['sql' => 'ATTACH DATABASE ":memory:" AS other;', 'mode' => 'stop'])
        ->assertOk()
        ->assertJsonPath('data.executed', 0)
        ->assertJsonPath('data.failed', 1);
});

it('imports the lines of a CSV file into a table, as the columns are mapped', function () {
    $file = UploadedFile::fake()->createWithContent('people.csv', "\u{FEFF}nom;ville;age\nLinus;Helsinki;54\nKen;;80\n");

    $this->actingAs($this->user)->post(databaseUri('/tables/customers/import'), [
        'file' => $file,
        'mapping' => ['name', 'city', ''],
        'delimiter' => ';',
        'header' => '1',
        'emptyAsNull' => '1',
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.inserted', 2);

    expect(importConnection()->table('customers')->where('id', '>', 3)->get(['name', 'city'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['name' => 'Linus', 'city' => 'Helsinki'], ['name' => 'Ken', 'city' => null]]);
});

it('runs the statements of the console and returns their rows', function () {
    $response = $this->actingAs($this->user)->postJson(databaseUri('/database/query'), [
        'sql' => "UPDATE customers SET city = 'Rome' WHERE id = 3; SELECT id, name, name FROM customers ORDER BY id DESC",
    ]);

    $response->assertOk()
        ->assertJsonPath('data.error', null)
        ->assertJsonPath('data.results.0.affected', 1)
        ->assertJsonPath('data.results.1.columns', ['id', 'name', 'name'])
        ->assertJsonPath('data.results.1.rows.0', [3, 'Alan', 'Alan']);

    $this->actingAs($this->user)->postJson(databaseUri('/database/query'), ['sql' => 'SELECT 1; SELECT * FROM nowhere'])
        ->assertOk()
        ->assertJsonCount(1, 'data.results')
        ->assertJsonPath('data.error.index', 2);
});
