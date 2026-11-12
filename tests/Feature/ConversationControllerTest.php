<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Ai\Middleware\StopWhenCancelled;
use App\Ai\Services\AnswerCancellation;
use App\Ai\Services\ProviderManagerService;
use App\Jobs\GenerateConversationTitle;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Exceptions\StreamErrorException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Streaming\Events\Error;

/**
 * @param  list<array<string, mixed>>  $toolCalls
 */
function storeAssistantTurn(Conversation $conversation, array $toolCalls, MessageStatus $status): void
{
    ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'steps' => [[
            'content' => '',
            'tool_calls' => $toolCalls,
            'reasoning' => '',
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ]],
        'usage' => [],
        'meta' => [],
        'status' => $status,
    ]);
}

/**
 * @param  array<string, mixed>  $settings
 */
function postChatMessage(User $user, Conversation $conversation, array $settings = []): TestResponse
{
    connectAnthropic();

    return test()->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'submit-message',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Salut']]],
        ],
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        ...$settings,
    ]);
}

function connectAnthropic(?string $apiUrl = 'https://anthropic.test/v1'): void
{
    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'api_url' => $apiUrl,
        'models' => [
            ['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true],
            ['id' => 'claude-haiku-5-5', 'label' => 'Claude Haiku 5.5', 'context_window' => 1000000, 'active' => false],
        ],
    ]);
}

it('renders a tool call waiting for approval with its reason', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    storeAssistantTurn($conversation, [
        ['id' => 'call_1', 'name' => 'CreateRecord', 'arguments' => ['table' => 'users'], 'approval_reason' => 'Saisie utilisateur requise.'],
    ], MessageStatus::Paused);

    $response = $this->actingAs($user)->get("/{$project->id}/chat/{$conversation->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialMessages.0.parts.0', [
                'type' => 'tool-CreateRecord',
                'toolCallId' => 'call_1',
                'state' => 'approval-requested',
                'input' => ['table' => 'users'],
                'approval' => ['id' => 'call_1', 'reason' => 'Saisie utilisateur requise.'],
            ])
        );
});

it('renders an answered tool call with the result stored on the call', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    storeAssistantTurn($conversation, [
        ['id' => 'call_1', 'name' => 'ListTables', 'arguments' => [], 'result' => '["users"]'],
    ], MessageStatus::Completed);

    $response = $this->actingAs($user)->get("/{$project->id}/chat/{$conversation->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialMessages.0.parts.0', [
                'type' => 'tool-ListTables',
                'toolCallId' => 'call_1',
                'input' => [],
                'state' => 'output-available',
                'output' => '["users"]',
            ])
        );
});

it('renders a tool call rejected by the user as denied', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    storeAssistantTurn($conversation, [
        ['id' => 'call_1', 'name' => 'WriteQuery', 'arguments' => ['query' => 'DELETE FROM users WHERE id = 1'], 'result' => 'Refusé.', 'denied' => true],
    ], MessageStatus::Completed);

    $response = $this->actingAs($user)->get("/{$project->id}/chat/{$conversation->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialMessages.0.parts.0', [
                'type' => 'tool-WriteQuery',
                'toolCallId' => 'call_1',
                'input' => ['query' => 'DELETE FROM users WHERE id = 1'],
                'state' => 'output-denied',
            ])
        );
});

it('rejects a chat request without messages', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", ['messages' => []]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['messages']);
});

it('streams the provider and the model of the answer and stores the exchange in the conversation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake(['Bonjour !']);

    $response = postChatMessage($user, $conversation);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('"type":"data-provider"')
        ->toContain('"type":"data-model"');
    expect($conversation->messages()->oldest()->get()->map->only(['role', 'content'])->all())->toBe([
        ['role' => 'user', 'content' => 'Salut'],
        ['role' => 'assistant', 'content' => 'Bonjour !'],
    ]);
});

it('answers with the selected provider and model, using the stored credentials', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    config(['ai.providers.anthropic.key' => 'key-from-the-environment']);
    SQLDatabaseAgent::fake(['Bonjour !']);

    $response = postChatMessage($user, $conversation);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('"type":"data-provider","id":"provider","data":"anthropic"')
        ->toContain('"type":"data-model","id":"model","data":"claude-opus-5-5"')
        ->and(config('ai.providers.anthropic.key'))->toBe('stored-anthropic-key')
        ->and(config('ai.providers.anthropic.url'))->toBe('https://anthropic.test/v1');
});

it('answers with a custom provider through the OpenAI-compatible driver', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    Provider::query()->create([
        'id' => 'my-llm',
        'label' => 'My LLM',
        'api_key' => 'sk-custom',
        'api_url' => 'https://llm.example.test/v1',
        'custom_openai_compatible' => true,
        'models' => [['id' => 'llama-4', 'label' => 'Llama 4', 'context_window' => null, 'active' => true]],
    ]);
    SQLDatabaseAgent::fake(['Bonjour !']);

    $response = postChatMessage($user, $conversation, ['provider' => 'my-llm', 'model' => 'llama-4']);

    $response->assertOk();
    expect($response->streamedContent())->toContain('"type":"data-provider","id":"provider","data":"my-llm"')
        ->and(config('ai.providers.my-llm'))->toBe(['driver' => 'openai-compatible', 'key' => 'sk-custom', 'url' => 'https://llm.example.test/v1']);
});

it('gives the agent the selected access mode and effort', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake(['Bonjour !']);

    $response = postChatMessage($user, $conversation, [
        'access_mode' => 'read-write',
        'effort' => 'high',
    ]);

    $response->assertOk();
    $response->streamedContent();
    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->accessMode === AgentAccessModeEnum::ReadWrite
        && $prompt->agent->effort === AgentEffortEnum::High
        && $prompt->agent->providerOptions(Lab::Anthropic) === [
            'thinking' => ['type' => 'adaptive'],
            'output_config' => ['effort' => 'high'],
        ]);
});

it('keeps the agent read-only at the default effort unless the request asks otherwise', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake(['Bonjour !']);

    $response = postChatMessage($user, $conversation);

    $response->assertOk();
    $response->streamedContent();
    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->accessMode === AgentAccessModeEnum::ReadOnly
        && $prompt->agent->effort === AgentEffortEnum::Default);
});

it('rejects settings the agent cannot run with', function (array $settings, string $invalidField) {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    Provider::query()->updateOrCreate(['id' => 'openai'], ['label' => 'Openai', 'api_key' => null, 'models' => null]);

    $response = postChatMessage($user, $conversation, $settings);

    $response->assertUnprocessable()->assertJsonValidationErrors([$invalidField]);
})->with([
    'unknown access mode' => [['access_mode' => 'admin'], 'access_mode'],
    'unknown effort' => [['effort' => 'extreme'], 'effort'],
    'provider without API key' => [['provider' => 'openai', 'model' => 'gpt-5.1'], 'provider'],
    'no provider' => [['provider' => null, 'model' => null], 'provider'],
    'provider without model' => [['model' => null], 'model'],
    'model of another provider' => [['provider' => 'anthropic', 'model' => 'gpt-5.1'], 'model'],
    'deactivated model' => [['provider' => 'anthropic', 'model' => 'claude-haiku-5-5'], 'model'],
]);

it('lets the SDK use the default URL of a provider without a stored one', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake(['Bonjour !']);
    connectAnthropic(apiUrl: null);
    ProviderManagerService::for(Provider::find('anthropic'));

    expect(config('ai.providers.anthropic'))->toBe(['driver' => 'anthropic', 'key' => 'stored-anthropic-key']);
});

it('titles a new conversation with the provider and the model selected by the user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    connectAnthropic();
    AnonymousAgent::fake(['Liste des tables']);

    $response = $this->actingAs($user)->postJson("/{$project->id}/conversations", [
        'message' => 'Quelles sont les tables ?',
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
    ]);

    $response->assertOk();
    expect(Conversation::find($response->json('conversation.id'))->title)->toBe('Liste des tables')
        ->and(config('ai.providers.anthropic.key'))->toBe('stored-anthropic-key');
    AnonymousAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'anthropic'
        && $prompt->model === 'claude-opus-5-5');
});

it('titles a new conversation with its message when the provider fails', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    connectAnthropic();
    AnonymousAgent::fake([fn () => throw new RuntimeException('Provider down')]);

    $response = $this->actingAs($user)->postJson("/{$project->id}/conversations", [
        'message' => 'Quelles sont les tables ?',
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
    ]);

    $response->assertOk();
    expect(Conversation::find($response->json('conversation.id'))->title)->toBe('Quelles sont les tables ?');
});

it('starts a conversation without waiting for its title', function () {
    Queue::fake();
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    connectAnthropic();

    $response = $this->actingAs($user)->postJson("/{$project->id}/conversations", [
        'message' => 'Quelles sont les tables ?',
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
    ]);

    $response->assertOk()->assertJsonPath('conversation.title', null);
    Queue::assertPushed(GenerateConversationTitle::class, fn (GenerateConversationTitle $job): bool => $job->conversation->id === $response->json('conversation.id')
        && $job->message === 'Quelles sont les tables ?'
        && $job->provider === Lab::Anthropic
        && $job->model === 'claude-opus-5-5');
});

it('titles a conversation with its message when the title job fails', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    connectAnthropic();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);

    new GenerateConversationTitle($conversation, 'Quelles sont les tables ?', Lab::Anthropic, 'claude-opus-5-5')
        ->failed(new RuntimeException('Worker killed'));

    expect($conversation->fresh()->title)->toBe('Quelles sont les tables ?');
});

it('returns the conversation so the page can show its title once generated', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id, 'title' => 'Liste des tables']);

    $this->actingAs($user)->getJson("/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('conversation.title', 'Liste des tables');
});

it('refuses to start a conversation without a usable model', function (array $selection, string $invalidField) {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    connectAnthropic();

    $response = $this->actingAs($user)->postJson("/{$project->id}/conversations", [
        'message' => 'Quelles sont les tables ?',
        ...$selection,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors([$invalidField]);
})->with([
    'nothing selected' => [[], 'provider'],
    'deactivated model' => [['provider' => 'anthropic', 'model' => 'claude-haiku-5-5'], 'model'],
]);

it('streams the message explaining a provider error just before the error part', function (?Error $error, string $message) {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake([fn () => throw new StreamErrorException($error)]);

    $response = postChatMessage($user, $conversation);

    $response->assertOk();
    $parts = collect(explode("\n\n", trim($response->streamedContent())))
        ->map(fn (string $frame): mixed => json_decode(Str::after($frame, 'data: '), true))
        ->filter()
        ->values()
        ->all();
    expect(array_slice($parts, -2))->toBe([
        ['type' => 'data-stream-error', 'data' => ['message' => $message], 'transient' => true],
        ['type' => 'error', 'errorText' => (new StreamErrorException($error))->getMessage()],
    ]);
})->with([
    'with the provider error' => [
        new Error('error_1', 'overloaded_error', 'Overloaded', false, 0),
        'Le fournisseur d\'IA a renvoyé une erreur : « Overloaded »',
    ],
    'without a provider error' => [
        null,
        'Le fournisseur d\'IA a interrompu la réponse avant la fin. Veuillez réessayer.',
    ],
]);

it('streams the message explaining a provider failure just before the masked error part', function (Throwable $exception, string $message) {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake([fn () => throw $exception]);

    $response = postChatMessage($user, $conversation);

    $response->assertOk();
    $parts = collect(explode("\n\n", trim($response->streamedContent())))
        ->map(fn (string $frame): mixed => json_decode(Str::after($frame, 'data: '), true))
        ->filter()
        ->values()
        ->all();
    expect(array_slice($parts, -2))->toBe([
        ['type' => 'data-stream-error', 'data' => ['message' => $message], 'transient' => true],
        ['type' => 'error', 'errorText' => 'An error occurred.'],
    ]);
})->with([
    'rate limited' => [
        fn () => RateLimitedException::forProvider('gemini', 429),
        'Le fournisseur d\'IA reçoit trop de requêtes pour le moment. Patientez quelques instants avant de réessayer.',
    ],
    'overloaded' => [
        fn () => ProviderOverloadedException::forProvider('gemini', 503),
        'Le fournisseur d\'IA est surchargé pour le moment. Veuillez réessayer dans quelques instants.',
    ],
    'insufficient credits' => [
        fn () => InsufficientCreditsException::forProvider('gemini', 402),
        'Le compte du fournisseur d\'IA n\'a plus assez de crédits ou de quota pour répondre.',
    ],
    'connection' => [
        fn () => ProviderConnectionException::forProvider('gemini'),
        'Impossible de joindre le fournisseur d\'IA. Vérifiez la connexion puis réessayez.',
    ],
]);

it('keeps any other failure masked without explaining it', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake([fn () => throw new RuntimeException('SQLSTATE secret detail')]);

    $response = postChatMessage($user, $conversation);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('"type":"error","errorText":"An error occurred."')
        ->not->toContain('data-stream-error')
        ->not->toContain('SQLSTATE secret detail');
});

it('renders an answer after its tool calls, followed by the provider and the model that wrote it', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    $message = ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'assistant',
        'content' => 'Il y a une table.',
        'attachments' => [],
        'steps' => [
            ['content' => '', 'tool_calls' => [['id' => 'call_1', 'name' => 'ListTables', 'arguments' => [], 'result' => '["users"]']], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []],
            ['content' => 'Il y a une table.', 'tool_calls' => [], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []],
        ],
        'usage' => [],
        'meta' => ['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite'],
        'status' => MessageStatus::Completed,
    ]);

    $response = $this->actingAs($user)->get("/{$project->id}/chat/{$conversation->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialMessages.0.parts.0.type', 'tool-ListTables')
            ->where('initialMessages.0.parts.1', ['type' => 'text', 'text' => 'Il y a une table.'])
            ->where('initialMessages.0.parts.2', ['type' => 'data-provider', 'id' => "data-provider-{$message->id}-gemini", 'data' => 'gemini'])
            ->where('initialMessages.0.parts.3', ['type' => 'data-model', 'id' => "data-model-{$message->id}-gemini-3.5-flash-lite", 'data' => 'gemini-3.5-flash-lite'])
        );
});

it('renders the text a step wrote before the tools it called, as it was streamed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'assistant',
        'content' => "Je regarde les tables.\n\nIl y a une table.",
        'attachments' => [],
        'steps' => [
            ['content' => 'Je regarde les tables.', 'tool_calls' => [['id' => 'call_1', 'name' => 'ListTables', 'arguments' => [], 'result' => '["users"]']], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []],
            ['content' => 'Il y a une table.', 'tool_calls' => [], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []],
        ],
        'usage' => [],
        'meta' => ['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'],
        'status' => MessageStatus::Completed,
    ]);

    $response = $this->actingAs($user)->get("/{$project->id}/chat/{$conversation->id}");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialMessages.0.parts.0', ['type' => 'text', 'text' => 'Je regarde les tables.'])
            ->where('initialMessages.0.parts.1.type', 'tool-ListTables')
            ->where('initialMessages.0.parts.1.state', 'output-available')
            ->where('initialMessages.0.parts.2', ['type' => 'text', 'text' => 'Il y a une table.'])
            ->where('initialMessages.0.parts.3.type', 'data-provider')
        );
});

function storeUserMessage(Conversation $conversation, string $content): void
{
    ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'user',
        'content' => $content,
        'attachments' => [],
        'steps' => [],
        'usage' => [],
        'meta' => [],
        'status' => MessageStatus::Completed,
    ]);
}

it('finds the conversations of the project whose title or messages contain the search', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $byTitle = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id, 'title' => 'Ventes de Janvier']);
    $byMessage = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id, 'title' => 'Analyse']);
    storeUserMessage($byMessage, 'Quel est le total des ventes par client ?');
    $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id, 'title' => 'Liste des tables']);
    $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id, 'title' => 'Ventes archivées', 'is_archived' => true]);
    $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => Project::factory()->for($user)->create()->id, 'title' => 'Ventes ailleurs']);
    $otherUser = User::factory()->create();
    $otherUser->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id, 'title' => 'Ventes d\'un autre']);

    $response = $this->actingAs($user)->getJson("/{$project->id}/conversations/search?search=ventes");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('excerpt', 'id')->all())->toEqual([
            $byTitle->id => null,
            $byMessage->id => 'Quel est le total des ventes par client ?',
        ]);
});

it('refuses a search without terms', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson("/{$project->id}/conversations/search")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search']);
});

/**
 * @param  list<string>  $userMessages
 */
function postRegeneration(User $user, Conversation $conversation, array $userMessages): TestResponse
{
    connectAnthropic();

    return test()->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'regenerate-message',
        'messages' => collect($userMessages)->map(fn (string $text, int $index) => [
            'id' => "msg_{$index}",
            'role' => 'user',
            'parts' => [['type' => 'text', 'text' => $text]],
        ])->all(),
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
    ]);
}

function storeAnswer(Conversation $conversation, string $content, array $meta = []): ConversationMessage
{
    return ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'assistant',
        'content' => $content,
        'attachments' => [],
        'steps' => [['content' => $content, 'tool_calls' => [], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []]],
        'usage' => [],
        'meta' => ['provider' => 'anthropic', 'model' => 'claude-opus-5-5', ...$meta],
        'status' => MessageStatus::Completed,
    ]);
}

function conversationWithDriver(User $user): Conversation
{
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);

    return $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
}

it('replaces the last answer when the user asks for it again', function () {
    $user = User::factory()->create();
    $conversation = conversationWithDriver($user);
    storeUserMessage($conversation, 'Bonjour');
    storeAnswer($conversation, 'Salut !');
    storeUserMessage($conversation, 'Combien de tables ?');
    storeAnswer($conversation, 'Il y en a deux.');
    SQLDatabaseAgent::fake(['Il y en a trois.']);

    postRegeneration($user, $conversation, ['Bonjour', 'Combien de tables ?'])->assertOk()->streamedContent();

    expect($conversation->messages()->oldest()->orderBy('id')->get()->map->only(['role', 'content'])->all())->toBe([
        ['role' => 'user', 'content' => 'Bonjour'],
        ['role' => 'assistant', 'content' => 'Salut !'],
        ['role' => 'user', 'content' => 'Combien de tables ?'],
        ['role' => 'assistant', 'content' => 'Il y en a trois.'],
    ]);
});

it('keeps the previous exchanges when the message asked again is not saved yet', function () {
    $user = User::factory()->create();
    $conversation = conversationWithDriver($user);
    storeUserMessage($conversation, 'Bonjour');
    storeAnswer($conversation, 'Salut !');
    SQLDatabaseAgent::fake(['Il y en a trois.']);

    postRegeneration($user, $conversation, ['Bonjour', 'Combien de tables ?'])->assertOk()->streamedContent();

    expect($conversation->messages()->oldest()->orderBy('id')->pluck('content')->all())
        ->toBe(['Bonjour', 'Salut !', 'Combien de tables ?', 'Il y en a trois.']);
});

it('asks the answer being streamed to stop', function () {
    $user = User::factory()->create();
    $conversation = conversationWithDriver($user);

    $this->actingAs($user)->postJson("/conversations/{$conversation->id}/cancel")->assertNoContent();

    expect(app(AnswerCancellation::class)->isRequested($conversation->id))->toBeTrue();
});

it('saves an answer stopped by the user as interrupted', function () {
    $user = User::factory()->create();
    $conversation = conversationWithDriver($user);
    SQLDatabaseAgent::fake([function () use ($conversation) {
        app(AnswerCancellation::class)->request($conversation->id);

        return 'Je commence…';
    }]);

    postChatMessage($user, $conversation)->assertOk()->streamedContent();

    $answer = $conversation->messages()->where('role', 'assistant')->sole();
    expect($answer->meta['cancelled'])->toBeTrue()
        ->and(app(AnswerCancellation::class)->isRequested($conversation->id))->toBeFalse();
});

it('ignores a stop asked before the answer started', function () {
    $user = User::factory()->create();
    $conversation = conversationWithDriver($user);
    app(AnswerCancellation::class)->request($conversation->id);
    SQLDatabaseAgent::fake(['Bonjour !']);

    postChatMessage($user, $conversation)->assertOk()->streamedContent();

    expect($conversation->messages()->where('role', 'assistant')->sole())
        ->content->toBe('Bonjour !')
        ->meta->not->toHaveKey('cancelled');
});

it('stops the agent before its next step once the answer is stopped', function () {
    $cancellation = app(AnswerCancellation::class);
    $middleware = new StopWhenCancelled(fn () => 'conversation-id', $cancellation);
    $step = Mockery::mock(PendingStep::class);

    $cancellation->request('conversation-id');

    expect($middleware->handle($step, fn () => throw new LogicException('The step should not run.'))->stopped())->toBeTrue();
});

it('renders an answer stopped by the user as interrupted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    $answer = storeAnswer($conversation, 'Je commence…', ['cancelled' => true]);

    $this->actingAs($user)->get("/{$project->id}/chat/{$conversation->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialMessages.0.parts.3', ['type' => 'data-cancelled', 'id' => "data-cancelled-{$answer->id}", 'data' => true])
        );
});
