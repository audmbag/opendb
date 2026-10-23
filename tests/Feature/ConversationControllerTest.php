<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Exceptions\StreamErrorException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
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

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'submit-message',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Salut']]],
        ],
    ]);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('"type":"data-provider"')
        ->toContain('"type":"data-model"');
    expect($conversation->messages()->oldest()->get()->map->only(['role', 'content'])->all())->toBe([
        ['role' => 'user', 'content' => 'Salut'],
        ['role' => 'assistant', 'content' => 'Bonjour !'],
    ]);
});

it('streams the message explaining a provider error just before the error part', function (?Error $error, string $message) {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $project->driver_config()->create(['driver_id' => 'sqlite', 'database' => ':memory:']);
    $conversation = $user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);
    SQLDatabaseAgent::fake([fn () => throw new StreamErrorException($error)]);

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'submit-message',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Salut']]],
        ],
    ]);

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

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'submit-message',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Salut']]],
        ],
    ]);

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

    $response = $this->actingAs($user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'submit-message',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Salut']]],
        ],
    ]);

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
