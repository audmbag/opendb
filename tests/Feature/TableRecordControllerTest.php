<?php

use App\Ai\Tools\Database\CreateRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('record-form-').'.sqlite';
});

afterEach(function () {
    @unlink($this->databasePath);
});

function customersTable(Builder $schema): void
{
    $schema->create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('name')->nullable();
        $table->boolean('active')->default(true);
        $table->integer('age');
        $table->date('born_on')->nullable();
        $table->foreignId('referrer_id')->nullable()->constrained('customers');
    });
}

function pendingCreateRecordCall(string $table = 'customers'): array
{
    return ['id' => 'call_1', 'name' => 'CreateRecord', 'arguments' => ['table' => $table, 'known_values' => []], 'approval_reason' => 'Saisie utilisateur requise via formulaire.'];
}

it('describes a field for each column the user fills, leaving out the auto-incremented key', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, customersTable(...));

    $response = $this->actingAs($user)->getJson("/{$project->id}/tables/customers/record-form");

    $response->assertOk()->assertExactJson(['data' => [
        'table' => 'customers',
        'fields' => [
            ['name' => 'email', 'type' => 'string', 'db_type' => 'varchar', 'required' => true, 'nullable' => false, 'default' => null, 'max_length' => null, 'options' => null, 'unique' => true, 'references' => null],
            ['name' => 'name', 'type' => 'string', 'db_type' => 'varchar', 'required' => false, 'nullable' => true, 'default' => null, 'max_length' => null, 'options' => null, 'unique' => false, 'references' => null],
            ['name' => 'active', 'type' => 'boolean', 'db_type' => 'tinyint(1)', 'required' => false, 'nullable' => false, 'default' => '1', 'max_length' => null, 'options' => null, 'unique' => false, 'references' => null],
            ['name' => 'age', 'type' => 'integer', 'db_type' => 'integer', 'required' => true, 'nullable' => false, 'default' => null, 'max_length' => null, 'options' => null, 'unique' => false, 'references' => null],
            ['name' => 'born_on', 'type' => 'date', 'db_type' => 'date', 'required' => false, 'nullable' => true, 'default' => null, 'max_length' => null, 'options' => null, 'unique' => false, 'references' => null],
            ['name' => 'referrer_id', 'type' => 'integer', 'db_type' => 'integer', 'required' => false, 'nullable' => true, 'default' => null, 'max_length' => null, 'options' => null, 'unique' => false, 'references' => ['table' => 'customers', 'column' => 'id']],
        ],
    ]]);
});

it('returns 404 for a table missing from the project database', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, customersTable(...));

    $response = $this->actingAs($user)->getJson("/{$project->id}/tables/invoices/record-form");

    $response->assertNotFound();
});

it('keeps the submitted values of a pending call, converted for the database, until the call is approved', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, customersTable(...));
    $conversation = conversationWithToolCall($user, $project, pendingCreateRecordCall());

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/tool-calls/call_1/record", [
        'email' => 'ada@example.com',
        'name' => '',
        'active' => false,
        'age' => '36',
        'born_on' => '1815-12-10',
    ]);

    $response->assertOk();
    expect(Cache::get(CreateRecord::submissionCacheKey('call_1')))->toBe([
        'table' => 'customers',
        'values' => ['email' => 'ada@example.com', 'active' => false, 'age' => 36, 'born_on' => '1815-12-10'],
    ]);
});

it('rejects values that do not match the type of their column', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, customersTable(...));
    $conversation = conversationWithToolCall($user, $project, pendingCreateRecordCall());

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/tool-calls/call_1/record", [
        'age' => 'trente-six',
        'born_on' => 'hier',
    ]);

    $response->assertUnprocessable()->assertOnlyJsonValidationErrors(['email', 'age', 'born_on']);
    expect(Cache::has(CreateRecord::submissionCacheKey('call_1')))->toBeFalse();
});

it('rejects a value already used in a unique column', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, function (Builder $schema) {
        customersTable($schema);
        $schema->getConnection()->table('customers')->insert(['email' => 'ada@example.com', 'age' => 36]);
    });
    $conversation = conversationWithToolCall($user, $project, pendingCreateRecordCall());

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/tool-calls/call_1/record", [
        'email' => 'ada@example.com',
        'age' => 20,
    ]);

    $response->assertUnprocessable()->assertOnlyJsonValidationErrors(['email']);
});

it('rejects a reference to a row missing from the referenced table', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, customersTable(...));
    $conversation = conversationWithToolCall($user, $project, pendingCreateRecordCall());

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/tool-calls/call_1/record", [
        'email' => 'ada@example.com',
        'age' => 36,
        'referrer_id' => 99,
    ]);

    $response->assertUnprocessable()->assertOnlyJsonValidationErrors(['referrer_id']);
});

it('returns 404 when the tool call is no longer waiting for approval', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->databasePath, customersTable(...));
    $conversation = conversationWithToolCall($user, $project, [...pendingCreateRecordCall(), 'result' => 'Enregistrement créé.']);

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/tool-calls/call_1/record", [
        'email' => 'ada@example.com',
        'age' => 36,
    ]);

    $response->assertNotFound();
    expect(Cache::has(CreateRecord::submissionCacheKey('call_1')))->toBeFalse();
});

it('returns 404 when the conversation belongs to another user', function () {
    $owner = User::factory()->create();
    $project = projectWithSqliteDatabase($owner, $this->databasePath, customersTable(...));
    $conversation = conversationWithToolCall($owner, $project, pendingCreateRecordCall());

    $response = $this->actingAs(User::factory()->create())->postJson("/conversations/{$conversation->id}/tool-calls/call_1/record", [
        'email' => 'ada@example.com',
        'age' => 36,
    ]);

    $response->assertNotFound();
});
