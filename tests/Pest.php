<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Database\ExplainOutput;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a project whose database is the given SQLite file, holding the tables built by the given callback.
 *
 * @param  Closure(Builder): void  $schema
 */
function projectWithSqliteDatabase(User $user, string $databasePath, Closure $schema): Project
{
    touch($databasePath);

    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => $databasePath]);

    $schema($project->driver_config->getDriver()->getConnection()->getSchemaBuilder());

    return $project->refresh();
}

/**
 * Create a conversation of the user whose last assistant message made the given tool call.
 *
 * @param  array<string, mixed>  $toolCall
 */
function conversationWithToolCall(User $user, Project $project, array $toolCall, MessageStatus $status = MessageStatus::Paused): Conversation
{
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);

    ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'steps' => [['content' => '', 'tool_calls' => [$toolCall], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []]],
        'usage' => [],
        'meta' => [],
        'status' => $status,
    ]);

    return $conversation;
}

/**
 * A small shop: three customers (one without city) and the orders of two of them.
 */
function shopDatabase(Builder $schema): void
{
    $schema->create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('city')->nullable();
    });

    $schema->create('orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('customer_id')->constrained('customers');
        $table->integer('total');
        $table->string('status');
    });

    $schema->getConnection()->table('customers')->insert([
        ['name' => 'Ada', 'city' => 'Paris'],
        ['name' => 'Grace', 'city' => 'Lyon'],
        ['name' => 'Alan', 'city' => null],
    ]);

    $schema->getConnection()->table('orders')->insert([
        ['customer_id' => 1, 'total' => 120, 'status' => 'paid'],
        ['customer_id' => 1, 'total' => 80, 'status' => 'pending'],
        ['customer_id' => 2, 'total' => 50, 'status' => 'paid'],
    ]);
}

/**
 * The output of EXPLAIN saved in tests/Fixtures/query-plans, with the query it explains.
 *
 * @return array{output: ExplainOutput, query: string}
 */
function queryPlanFixture(string $name): array
{
    $fixture = json_decode(file_get_contents(__DIR__."/Fixtures/query-plans/{$name}.json"), true);

    return [
        'output' => new ExplainOutput($fixture['raw'], $fixture['classic'], $fixture['version'], $fixture['tableRows'] ?? []),
        'query' => $fixture['query'],
    ];
}

/**
 * A project on the small shop database, with one active model so the chat does not ask which one to use.
 */
function chatProject(User $user): Project
{
    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'models' => [['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true]],
    ]);
    Provider::query()->where('id', '!=', 'anthropic')->update(['api_key' => null]);

    return projectWithSqliteDatabase($user, tempnam(sys_get_temp_dir(), 'chat').'.sqlite', shopDatabase(...));
}

/**
 * @param  array<string, mixed>  $arguments
 */
function openChat(User $user, Project $project, array $arguments = []): PendingCommand
{
    return test()->artisan('chat', ['--name' => $user->name, '--project' => $project->name, ...$arguments])
        ->expectsQuestion('Mot de passe', 'password');
}
