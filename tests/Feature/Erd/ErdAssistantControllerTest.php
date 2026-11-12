<?php

use App\Ai\Agents\Erd\SchemaDesignerAgent;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Ai\Services\PausedTurnStore;
use App\Ai\Tools\Erd\ProposeSchemaChanges;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('erd-assistant-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));

    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'api_url' => 'https://anthropic.test/v1',
        'models' => [['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true]],
    ]);
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  list<array<string, mixed>>  $changes
 */
function previewSchemaChanges(array $changes, ?User $user = null)
{
    return test()->actingAs($user ?? test()->user)->postJson('/'.test()->project->id.'/erd/assistant/preview', ['changes' => $changes]);
}

it('draws the changes of the assistant as a draft with the statements they would run, without running them', function () {
    $response = previewSchemaChanges([
        ['action' => 'create_table', 'table' => 'invoices', 'columns' => [
            ['name' => 'id', 'type' => 'bigInteger', 'autoIncrement' => true, 'unsigned' => true],
            ['name' => 'customer_id', 'type' => 'bigInteger', 'unsigned' => true],
        ], 'foreignKeys' => [['columns' => ['customer_id'], 'referencesTable' => 'customers', 'referencesColumns' => ['id']]]],
        ['action' => 'alter_table', 'table' => 'customers', 'addColumns' => [['name' => 'email', 'type' => 'string', 'nullable' => true]], 'dropColumns' => ['city']],
        ['action' => 'drop_table', 'table' => 'orders'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.destructive', true)
        ->assertJsonPath('data.draft.dropped', ['orders'])
        ->assertJsonPath('data.draft.tables.0.name', 'invoices')
        ->assertJsonPath('data.draft.tables.0.draft', 'new')
        ->assertJsonPath('data.draft.tables.0.foreign_keys.0.references_table', 'customers')
        ->assertJsonPath('data.draft.tables.0.foreign_keys.0.draft', true)
        ->assertJsonPath('data.draft.tables.1.draft', 'changed')
        ->assertJsonPath('data.draft.tables.1.columns', [
            ['name' => 'id', 'type' => 'integer', 'nullable' => false, 'default' => null, 'draft' => null],
            ['name' => 'name', 'type' => 'varchar', 'nullable' => false, 'default' => null, 'draft' => null],
            ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => true, 'default' => null, 'draft' => 'added'],
            ['name' => 'city', 'type' => 'varchar', 'nullable' => true, 'default' => null, 'draft' => 'dropped'],
        ]);

    expect(implode("\n", $response->json('data.statements')))->toContain('create table "invoices"', 'drop table "orders"')
        ->and($this->project->driver_config->getDriver()->listTables(false))->toBe(['customers', 'orders']);
});

it('names what is wrong with the changes instead of drawing them', function () {
    previewSchemaChanges([['action' => 'alter_table', 'table' => 'invoices', 'dropColumns' => ['id']]])
        ->assertOk()
        ->assertJsonPath('data.valid', false)
        ->assertJsonPath('data.errors', ['alter_table invoices: La table invoices n’existe pas.'])
        ->assertJsonPath('data.statements', []);
});

it('requires the changes to draw', function () {
    previewSchemaChanges([])->assertUnprocessable()->assertJsonValidationErrors('changes');
});

it('keeps the assistant of a project away from another user', function () {
    previewSchemaChanges([['action' => 'drop_table', 'table' => 'orders']], User::factory()->create())->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->postJson("/{$this->project->id}/erd/assistant/chat", ['provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'messages' => []])
        ->assertForbidden();
});

it('answers in a stream with the earlier messages, the apply mode of the user and the tables the agent may read', function () {
    $this->user->update(['settings' => $this->user->settings->withSection('agent', ['schemaApplyMode' => 'additions', 'maxSteps' => 12])]);
    $this->project->driver_config->update(['disabled_tables' => ['orders']]);
    SchemaDesignerAgent::fake(['Table invoices proposée.']);

    $response = $this->actingAs($this->user)->postJson("/{$this->project->id}/erd/assistant/chat", [
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Que contient la base ?']]],
            ['id' => 'msg_2', 'role' => 'assistant', 'parts' => [['type' => 'text', 'text' => 'Une table customers.']]],
            ['id' => 'msg_3', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Ajoute une table invoices']]],
        ],
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('"delta":" invoices"');
    SchemaDesignerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Ajoute une table invoices'
        && array_map(fn ($message) => $message->content, $prompt->messages) === ['Que contient la base ?', 'Une table customers.']
        && $prompt->agent->mode === SchemaChangeApplyModeEnum::Additions
        && $prompt->agent->maxSteps() === 12
        && $prompt->agent->driver->listTables(false) === ['customers']
        && collect($prompt->agent->tools())->contains(fn ($tool) => $tool instanceof ProposeSchemaChanges && $tool->mode === SchemaChangeApplyModeEnum::Additions));
});

it('refuses a model that is not available', function () {
    SchemaDesignerAgent::fake();

    $this->actingAs($this->user)->postJson("/{$this->project->id}/erd/assistant/chat", [
        'provider' => 'anthropic',
        'model' => 'claude-haiku-1',
        'messages' => [['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Bonjour']]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('model');

    SchemaDesignerAgent::assertNeverPrompted();
});

it('resumes a turn paused for an approval with what the provider returned when it paused', function () {
    $toolCall = new ToolCall('call_1', 'ProposeSchemaChanges', ['changes' => []], thoughtSignature: 'signature-1');
    app(PausedTurnStore::class)->remember("erd:{$this->user->id}:{$this->project->id}", new StreamedAgentResponse('invocation', new Collection([
        new StreamStart('start', 'anthropic', 'claude-opus-5-5', 0),
        new ToolApprovalRequest('pause', new Collection([new PendingApproval('call_1', 'ProposeSchemaChanges', ['changes' => []])]), 0, new Collection([
            new Step('', [$toolCall], [], FinishReason::ToolCalls, new TextUsage, new Meta('anthropic', 'claude-opus-5-5'), '', [['type' => 'thinking', 'signature' => 'block-1']]),
        ])),
    ]), new Meta('anthropic', 'claude-opus-5-5')));
    SchemaDesignerAgent::fake(['Table créée.']);

    $this->actingAs($this->user)->postJson("/{$this->project->id}/erd/assistant/chat", [
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        'messages' => [
            ['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Ajoute une table invoices']]],
            ['id' => 'msg_2', 'role' => 'assistant', 'parts' => [[
                'type' => 'tool-ProposeSchemaChanges',
                'toolCallId' => 'call_1',
                'state' => 'approval-responded',
                'input' => ['changes' => []],
                'approval' => ['id' => 'call_1', 'approved' => true],
            ]]],
        ],
    ])->assertOk()->streamedContent();

    SchemaDesignerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->messages[1]->replayBlocks === [['type' => 'thinking', 'signature' => 'block-1']]
        && $prompt->messages[1]->toolCalls->first()->thoughtSignature === 'signature-1');
});
