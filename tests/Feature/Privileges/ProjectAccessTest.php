<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('project-access-').'.sqlite';
    $this->project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...));
    $this->user = User::factory()->create(['name' => 'alice']);
    $this->privileges = app(PrivilegeManager::class);
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * Give alice the privileges on the table of the shop, or on the whole project without a table.
 *
 * @param  list<PrivilegeEnum>  $privileges
 */
function grantOnShop(array $privileges, ?string $table = null): void
{
    test()->privileges->grant(null, test()->user, $privileges, test()->project, $table);
}

function activeAnthropicModel(): void
{
    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'models' => [['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true]],
    ]);
}

function chatOfAlice(array $settings = [], ?Conversation $conversation = null): TestResponse
{
    activeAnthropicModel();

    $conversation ??= test()->user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => test()->project->id]);

    return test()->actingAs(test()->user)->postJson("/conversations/{$conversation->id}/chat", [
        'id' => $conversation->id,
        'trigger' => 'submit-message',
        'messages' => [['id' => 'msg_1', 'role' => 'user', 'parts' => [['type' => 'text', 'text' => 'Salut']]]],
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        ...$settings,
    ]);
}

it('lists the projects the user holds a privilege on, and no other', function () {
    Project::factory()->create();
    grantOnShop([PrivilegeEnum::Select], 'orders');

    $this->actingAs($this->user)->getJson('/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->project->id);
});

it('refuses to open a project the user holds no privilege on', function () {
    $this->actingAs($this->user)->get("/{$this->project->id}")->assertForbidden();
});

it('refuses to change the settings of a project without MANAGE PROJECT', function () {
    grantOnShop([PrivilegeEnum::Select, PrivilegeEnum::Update]);

    $this->actingAs($this->user)->putJson("/projects/{$this->project->id}/settings/info", ['name' => 'Autre', 'description' => 'x'])
        ->assertForbidden();
});

it('hides the tables the user holds no privilege on from the schema', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');

    $tables = collect($this->actingAs($this->user)->getJson("/{$this->project->id}/schema/database")->assertOk()->json('data'))->pluck('name');

    expect($tables->all())->toBe(['orders']);
});

it('reads the rows of a table only with SELECT on it', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');

    $this->actingAs($this->user)->getJson("/{$this->project->id}/tables/orders/rows")->assertOk();
    $this->actingAs($this->user)->getJson("/{$this->project->id}/tables/customers/rows")->assertForbidden();
});

it('refuses a change of the grid without the privilege of its kind', function () {
    grantOnShop([PrivilegeEnum::Select, PrivilegeEnum::Update], 'orders');

    $this->actingAs($this->user)->postJson("/{$this->project->id}/tables/orders/changes", [
        'changes' => [['type' => 'delete', 'key' => ['id' => 1]]],
    ])->assertForbidden()->assertJsonPath('message', 'Commande DELETE refusée à l’utilisateur « alice » sur la table « orders ».');

    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->count())->toBe(3);
});

it('runs in the SQL editor only the statements the privileges allow', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');

    $this->actingAs($this->user)->postJson("/{$this->project->id}/sql-editor/run", ['sql' => "SELECT COUNT(*) FROM orders;\nDELETE FROM orders;"])
        ->assertOk()
        ->assertJsonPath('data.results.0.rows', [[3]])
        ->assertJsonPath('data.error.index', 2)
        ->assertJsonPath('data.error.message', 'Commande DELETE refusée à l’utilisateur « alice » sur la table « orders ».');

    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->count())->toBe(3);
});

it('keeps the secrets of the connection for who manages the project', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');

    $this->actingAs($this->user)->get("/{$this->project->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('project.driver_config.database')
            ->missing('project.driver_config.password')
            ->missing('project.driver_config.ssh')
            ->missing('project.driver_config.disabled_tables'));

    $this->actingAs($this->project->user)->get("/{$this->project->id}")
        ->assertInertia(fn (Assert $page) => $page->has('project.driver_config.disabled_tables'));
});

it('lets every user who reaches the project find their archived conversations', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');
    $this->user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $this->project->id, 'is_archived' => true]);

    $this->actingAs($this->user)->getJson("/projects/{$this->project->id}/settings/archived-conversations")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('tells a refused action in the language of the user', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');

    $this->actingAs($this->user)->putJson("/projects/{$this->project->id}/settings/memory", ['memory' => ''])
        ->assertForbidden()
        ->assertJsonPath('message', __('privileges.errors.unauthorized'));
});

it('starts a conversation for a user holding privileges on a single table', function () {
    Queue::fake();
    activeAnthropicModel();
    grantOnShop([PrivilegeEnum::Select, PrivilegeEnum::Update], 'orders');

    $this->actingAs($this->user)->postJson("/{$this->project->id}/conversations", [
        'message' => 'Combien de commandes ?',
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
    ])->assertOk()->assertJsonPath('conversation.project_id', $this->project->id);
});

it('refuses the chat once the user holds nothing on the project any more', function () {
    grantOnShop([PrivilegeEnum::Select], 'orders');
    $conversation = $this->user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $this->project->id]);
    $this->privileges->revoke(null, $this->user, [PrivilegeEnum::Select], $this->project, 'orders');

    chatOfAlice(conversation: $conversation)->assertForbidden();
});

it('keeps the agent read-only for a user who may not change the database', function () {
    grantOnShop([PrivilegeEnum::Select]);
    SQLDatabaseAgent::fake(['Bonjour !']);

    $response = chatOfAlice(['access_mode' => 'read-write']);

    $response->assertOk();
    $response->streamedContent();
    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->accessMode === AgentAccessModeEnum::ReadOnly
        && $prompt->agent->user->is($this->user));
});

it('keeps the conversations of a user out of reach of the root', function () {
    grantOnShop([PrivilegeEnum::Select]);
    $conversation = $this->user->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $this->project->id]);

    $this->actingAs(User::factory()->root()->create())->getJson("/conversations/{$conversation->id}")->assertNotFound();
});
