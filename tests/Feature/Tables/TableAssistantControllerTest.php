<?php

use App\Ai\Agents\Tables\QueryWriterAgent;
use App\Ai\Agents\Tables\TableChatAgent;
use App\Ai\Agents\Tables\TableFilterAgent;
use App\Ai\Agents\Tables\TableIndexAdvisorAgent;
use App\Ai\Agents\Tables\TableInsightsAgent;
use App\Ai\Tools\Database\WriteQuery;
use App\Models\Provider;
use App\Models\User;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('table-assistant-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));

    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'api_url' => 'https://anthropic.test/v1',
        'models' => [
            ['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true],
            ['id' => 'claude-haiku-5-5', 'label' => 'Claude Haiku 5.5', 'context_window' => 1000000, 'active' => false],
        ],
    ]);
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  array<string, mixed>  $data
 */
function askTableAssistant(string $action, array $data = [], string $table = 'customers')
{
    return test()->actingAs(test()->user)->postJson('/'.test()->project->id."/tables/{$table}/ai/{$action}", [
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        ...$data,
    ]);
}

it('turns a sentence into filters of the grid, with the model chosen by the user', function () {
    TableFilterAgent::fake([[
        'match' => 'all',
        'conditions' => [
            ['column' => 'city', 'operator' => 'is_not_null', 'value' => 'ignored'],
            ['column' => 'name', 'operator' => 'starts_with', 'value' => 'A'],
        ],
        'explanation' => 'Clients avec une ville dont le nom commence par A',
    ]]);

    $response = askTableAssistant('filter', ['prompt' => 'les clients avec une ville dont le nom commence par A']);

    $response->assertOk()->assertExactJson(['data' => [
        'match' => 'all',
        'filters' => [
            ['column' => 'city', 'operator' => 'is_not_null', 'value' => null],
            ['column' => 'name', 'operator' => 'starts_with', 'value' => 'A'],
        ],
        'explanation' => 'Clients avec une ville dont le nom commence par A',
    ]]);
    TableFilterAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'anthropic'
        && $prompt->model === 'claude-opus-5-5'
        && $prompt->agent->table === 'customers'
        && array_column($prompt->agent->columns, 'name') === ['id', 'name', 'city']);
});

it('refuses a filter naming a column the table does not have', function () {
    TableFilterAgent::fake([[
        'match' => 'all',
        'conditions' => [['column' => 'country', 'operator' => '=', 'value' => 'France']],
        'explanation' => 'Clients en France',
    ]]);

    askTableAssistant('filter', ['prompt' => 'les clients français'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('query');
});

it('needs a sentence to suggest a filter', function () {
    TableFilterAgent::fake();

    askTableAssistant('filter')->assertUnprocessable()->assertJsonValidationErrors('prompt');

    TableFilterAgent::assertNeverPrompted();
});

it('rejects a provider or a model the user cannot run', function (array $settings, string $invalidField) {
    Provider::query()->updateOrCreate(['id' => 'openai'], ['label' => 'Openai', 'api_key' => null, 'models' => null]);
    TableInsightsAgent::fake();

    askTableAssistant('insights', $settings)->assertUnprocessable()->assertJsonValidationErrors([$invalidField]);

    TableInsightsAgent::assertNeverPrompted();
})->with([
    'provider without API key' => [['provider' => 'openai', 'model' => 'gpt-5.1'], 'provider'],
    'no provider' => [['provider' => null], 'provider'],
    'model of another provider' => [['model' => 'gpt-5.1'], 'model'],
    'deactivated model' => [['model' => 'claude-haiku-5-5'], 'model'],
]);

it('explains a table from its structure and the profile of its columns', function () {
    TableInsightsAgent::fake([[
        'summary' => 'Les clients de la boutique.',
        'columns' => [['name' => 'city', 'meaning' => 'Ville de livraison']],
        'anomalies' => [['column' => 'city', 'issue' => 'Un client sur trois sans ville', 'severity' => 'info']],
        'questions' => ['Quels clients n\'ont jamais commandé ?'],
    ]]);

    askTableAssistant('insights')
        ->assertOk()
        ->assertJsonPath('data.summary', 'Les clients de la boutique.')
        ->assertJsonPath('data.anomalies.0.severity', 'info')
        ->assertJsonPath('data.questions', ['Quels clients n\'ont jamais commandé ?']);

    TableInsightsAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $context = json_decode($prompt->prompt, true);

        return $context['table']['name'] === 'customers'
            && $context['referencedBy'][0]['table'] === 'orders'
            && $context['profile']['totalRows'] === 3;
    });
});

it('suggests indexes from the plan of the filtered view', function () {
    TableIndexAdvisorAgent::fake([[
        'indexes' => [['statement' => 'CREATE INDEX orders_status_index ON orders (status);', 'columns' => ['status'], 'reason' => 'Évite le parcours complet', 'impact' => 'high']],
        'note' => 'La table est parcourue en entier.',
    ]]);

    $response = askTableAssistant('indexes', ['filters' => [['column' => 'status', 'operator' => '=', 'value' => 'paid']]], table: 'orders');

    $response->assertOk()
        ->assertJsonPath('data.indexes.0.statement', 'CREATE INDEX orders_status_index ON orders (status);')
        ->assertJsonPath('data.query', 'select * from "orders" where ("status" = \'paid\') order by "id" asc');
    TableIndexAdvisorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->driver === 'sqlite'
        && str_contains($prompt->prompt, '\"status\" = \'paid\''));
});

it('keeps the data of a table the agent may not read away from the provider', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);
    TableInsightsAgent::fake();

    askTableAssistant('insights')->assertForbidden();

    TableInsightsAgent::assertNeverPrompted();
});

it('explains why the provider did not answer', function () {
    TableInsightsAgent::fake([fn () => throw RateLimitedException::forProvider('anthropic', 429)]);

    askTableAssistant('insights')
        ->assertUnprocessable()
        ->assertJsonPath('errors.ai.0', __('chat.stream_errors.rate_limited'));
});

it('profiles the columns of a table without the agent', function () {
    $this->actingAs($this->user)
        ->getJson("/{$this->project->id}/tables/customers/profile")
        ->assertOk()
        ->assertJsonPath('data.totalRows', 3)
        ->assertJsonPath('data.columns.2.nullCount', 1);
});

/**
 * @param  array<string, mixed>  $data
 */
function askTableChat(array $data = [], string $table = 'orders')
{
    return askTableAssistant('chat', [
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Combien de commandes ?']]],
            ['id' => 'msg_2', 'role' => 'assistant', 'parts' => [['type' => 'text', 'text' => 'Il y a 3 commandes.']]],
            ['id' => 'msg_3', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Et payées ?']]],
        ],
        ...$data,
    ], $table);
}

it('answers a quick question about the table with the earlier messages and the view the user is looking at', function () {
    TableChatAgent::fake(['Deux commandes payées.']);

    $response = askTableChat(['filters' => [['column' => 'status', 'operator' => '=', 'value' => 'paid']]]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('"delta":" commandes"')->toContain('"type":"data-model"');
    TableChatAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Et payées ?'
        && $prompt->model === 'claude-opus-5-5'
        && array_map(fn ($message) => $message->content, $prompt->messages) === ['Combien de commandes ?', 'Il y a 3 commandes.']
        && $prompt->agent->table === 'orders'
        && str_contains($prompt->agent->view, '"status" = \'paid\'')
        && collect($prompt->agent->tools())->doesntContain(fn ($tool) => $tool instanceof WriteQuery));
});

it('keeps a quick question about a table the agent may not read away from the provider', function () {
    $this->project->driver_config->update(['disabled_tables' => ['orders']]);
    TableChatAgent::fake();

    askTableChat()->assertForbidden();

    TableChatAgent::assertNeverPrompted();
});

it('hides the tables the agent may not read from a quick question about another table', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);
    TableChatAgent::fake(['Trois commandes.']);

    askTableChat()->assertOk()->streamedContent();

    TableChatAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->driver->listTables(false) === ['orders']);
});

it('refuses a quick question about a table that does not exist', function () {
    TableChatAgent::fake();

    askTableChat(table: 'invoices')->assertNotFound();

    TableChatAgent::assertNeverPrompted();
});

/**
 * @param  array<string, mixed>  $data
 */
function askQueryWriter(array $data = [])
{
    return test()->actingAs(test()->user)->postJson('/'.test()->project->id.'/database/query/write', [
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        'prompt' => 'les commandes payées',
        ...$data,
    ]);
}

it('writes the statements the user describes, naming only the tables the agent may read', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);
    QueryWriterAgent::fake([[
        'sql' => "SELECT * FROM orders WHERE status = 'paid' LIMIT 100;",
        'explanation' => 'Les commandes payées.',
    ]]);

    askQueryWriter(['script' => 'SELECT COUNT(*) FROM orders;'])
        ->assertOk()
        ->assertExactJson(['data' => [
            'sql' => "SELECT * FROM orders WHERE status = 'paid' LIMIT 100;",
            'explanation' => 'Les commandes payées.',
        ]]);

    QueryWriterAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'les commandes payées'
        && $prompt->model === 'claude-opus-5-5'
        && $prompt->agent->tables === ['orders']
        && $prompt->agent->script === 'SELECT COUNT(*) FROM orders;');
});

it('explains that the agent wrote nothing', function () {
    QueryWriterAgent::fake([['sql' => '  ', 'explanation' => '']]);

    askQueryWriter()
        ->assertUnprocessable()
        ->assertJsonPath('errors.query.0', "L'assistant n'a pas écrit de requête. Reformulez la demande.");
});

it('explains why the provider did not write the statements', function () {
    QueryWriterAgent::fake([fn () => throw RateLimitedException::forProvider('anthropic', 429)]);

    askQueryWriter()
        ->assertUnprocessable()
        ->assertJsonPath('errors.ai.0', __('chat.stream_errors.rate_limited'));
});
