<?php

use App\Ai\Agents\Tables\QueryReviewerAgent;
use App\Models\Provider;
use App\Models\User;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-review-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));

    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'api_url' => 'https://anthropic.test/v1',
        'models' => [
            ['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true],
        ],
    ]);
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  array<string, mixed>  $data
 */
function askQueryReview(array $data)
{
    return test()->actingAs(test()->user)->postJson('/'.test()->project->id.'/sql-editor/review', [
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        ...$data,
    ]);
}

it('fixes a statement from the error of the database, with the model chosen by the user', function () {
    QueryReviewerAgent::fake([[
        'explanation' => 'La table s’appelle orders.',
        'sql' => 'SELECT * FROM orders;',
    ]]);

    askQueryReview(['mode' => 'fix', 'sql' => 'SELECT * FROM order', 'error' => 'no such table: order'])
        ->assertOk()
        ->assertExactJson(['data' => ['explanation' => 'La table s’appelle orders.', 'sql' => 'SELECT * FROM orders;']]);

    QueryReviewerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'claude-opus-5-5'
        && $prompt->agent->mode === 'fix'
        && $prompt->agent->sql === 'SELECT * FROM order'
        && $prompt->agent->error === 'no such table: order'
        && $prompt->agent->plan === null);
});

it('optimizes a SELECT from the summary of its execution plan', function () {
    QueryReviewerAgent::fake([['explanation' => 'Un index sur status évite le parcours complet.', 'sql' => 'CREATE INDEX orders_status ON orders (status);']]);

    askQueryReview(['mode' => 'optimize', 'sql' => "SELECT * FROM orders WHERE status = 'paid'"])->assertOk();

    QueryReviewerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->plan['alerts'][0]['code'] === 'FULL_SCAN');
});

it('optimizes a statement without a plan, like an UPDATE, without running it', function () {
    QueryReviewerAgent::fake([['explanation' => 'Déjà efficace.', 'sql' => '']]);

    askQueryReview(['mode' => 'optimize', 'sql' => "UPDATE orders SET status = 'paid'"])
        ->assertOk()
        ->assertJsonPath('data.sql', null);

    QueryReviewerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->plan === null);
    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->where('status', 'paid')->count())->toBe(2);
});

it('explains that the agent gave no explanation', function () {
    QueryReviewerAgent::fake([['explanation' => ' ', 'sql' => '']]);

    askQueryReview(['mode' => 'explain', 'sql' => 'SELECT 1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.query.0', "L'assistant n'a pas répondu. Réessayez.");
});

it('requires the error of the database to fix a statement, and a known mode', function () {
    QueryReviewerAgent::fake();

    askQueryReview(['mode' => 'fix', 'sql' => 'SELECT 1'])->assertJsonValidationErrors(['error']);
    askQueryReview(['mode' => 'translate', 'sql' => 'SELECT 1'])->assertJsonValidationErrors(['mode']);

    QueryReviewerAgent::assertNeverPrompted();
});

it('keeps the review of a project away from other users', function () {
    QueryReviewerAgent::fake();

    $this->actingAs(User::factory()->create())
        ->postJson("/{$this->project->id}/sql-editor/review", ['provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'mode' => 'explain', 'sql' => 'SELECT 1'])
        ->assertForbidden();

    QueryReviewerAgent::assertNeverPrompted();
});
