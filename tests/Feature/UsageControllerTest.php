<?php

use App\Ai\Enums\AiFeatureEnum;
use App\Models\Provider;
use App\Models\UsageRecord;
use App\Models\User;
use Laravel\Ai\AnonymousAgent;

use function Laravel\Ai\agent;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
});

it('records the provider, the model and the feature of every call to a model', function () {
    AnonymousAgent::fake(['Liste des tables']);

    agent(instructions: 'Titre')->prompt('Les tables ?', provider: 'anthropic', model: 'claude-opus-5-5');

    expect(UsageRecord::query()->sole())
        ->provider->toBe('anthropic')
        ->model->toBe('claude-opus-5-5')
        ->feature->toBe(AiFeatureEnum::ConversationTitle)
        ->failed->toBeFalse();
});

it('records a call the provider failed to answer', function () {
    AnonymousAgent::fake([fn () => throw new RuntimeException('Provider down')]);

    rescue(fn () => agent(instructions: 'Titre')->prompt('Les tables ?', provider: 'anthropic', model: 'claude-opus-5-5'), report: false);

    expect(UsageRecord::query()->sole())
        ->failed->toBeTrue()
        ->input_tokens->toBe(0);
});

it('sums the usage of each model of each provider over the period', function () {
    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'models' => [['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true]],
    ]);
    UsageRecord::factory()->create(['input_tokens' => 1000, 'output_tokens' => 100, 'cache_read_input_tokens' => 400, 'duration' => 1000, 'invocation_id' => 'run-1']);
    UsageRecord::factory()->create(['input_tokens' => 3000, 'output_tokens' => 300, 'reasoning_tokens' => 50, 'duration' => 3000, 'invocation_id' => 'run-1', 'created_at' => now()->subDays(2)]);
    UsageRecord::factory()->failed()->create(['duration' => 9000, 'invocation_id' => 'run-2']);
    UsageRecord::factory()->create(['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite', 'feature' => AiFeatureEnum::TableFilter, 'input_tokens' => 500, 'output_tokens' => 20]);
    UsageRecord::factory()->create(['input_tokens' => 99999, 'output_tokens' => 0, 'created_at' => now()->subDays(10)]);

    $response = $this->actingAs($this->user)->getJson('/settings/usage?period=7');

    $response->assertOk()
        ->assertJsonPath('data.period', ['days' => 7, 'from' => '2026-10-31', 'to' => '2026-11-06'])
        ->assertJsonPath('data.totals.requests', 4)
        ->assertJsonPath('data.totals.input_tokens', 4500)
        ->assertJsonPath('data.totals.failures', 1)
        ->assertJsonPath('data.totals.providers', 2)
        ->assertJsonPath('data.previous.input_tokens', 99999)
        ->assertJsonPath('data.providers.0.id', 'anthropic')
        ->assertJsonPath('data.providers.0.label', 'Anthropic')
        ->assertJsonPath('data.providers.1.label', 'Gemini')
        ->assertJsonPath('data.features.1', ['id' => 'table_filter', 'feature' => 'table_filter', 'requests' => 1, 'tokens' => 520]);

    expect($response->json('data.providers.0.models.0'))->toMatchArray([
        'id' => 'claude-opus-5-5',
        'label' => 'Claude Opus 5.5',
        'requests' => 3,
        'runs' => 2,
        'input_tokens' => 4000,
        'output_tokens' => 400,
        'cache_read_input_tokens' => 400,
        'reasoning_tokens' => 50,
        'failures' => 1,
        'average_duration' => 2000,
    ]);
});

it('draws the tokens of each provider on every day of the period', function () {
    UsageRecord::factory()->create(['input_tokens' => 1000, 'output_tokens' => 100]);
    UsageRecord::factory()->create(['provider' => 'gemini', 'input_tokens' => 500, 'output_tokens' => 20]);
    UsageRecord::factory()->create(['input_tokens' => 200, 'output_tokens' => 0, 'created_at' => now()->subDays(3)]);

    $points = $this->actingAs($this->user)->getJson('/settings/usage?period=7')->json('data.timeline.points');

    expect($points)->toHaveCount(7)
        ->and($points[3])->toBe(['date' => '2026-11-03', 'total' => 200, 'providers' => ['anthropic' => 200]])
        ->and($points[4])->toBe(['date' => '2026-11-04', 'total' => 0, 'providers' => []])
        ->and($points[6])->toBe(['date' => '2026-11-06', 'total' => 1620, 'providers' => ['anthropic' => 1100, 'gemini' => 520]]);
});

it('draws one point per month for the whole of a long history', function () {
    UsageRecord::factory()->create(['input_tokens' => 1000, 'output_tokens' => 0, 'created_at' => now()->subMonths(6)]);
    UsageRecord::factory()->create(['input_tokens' => 50, 'output_tokens' => 0]);

    $response = $this->actingAs($this->user)->getJson('/settings/usage?period=all');

    $response->assertOk()
        ->assertJsonPath('data.period.days', null)
        ->assertJsonPath('data.period.from', '2026-05-06')
        ->assertJsonPath('data.previous', null)
        ->assertJsonPath('data.timeline.bucket', 'month')
        ->assertJsonCount(7, 'data.timeline.points')
        ->assertJsonPath('data.timeline.points.0', ['date' => '2026-05', 'total' => 1000, 'providers' => ['anthropic' => 1000]])
        ->assertJsonPath('data.timeline.points.6', ['date' => '2026-11', 'total' => 50, 'providers' => ['anthropic' => 50]]);
});

it('refuses an unknown period', function () {
    $this->actingAs($this->user)->getJson('/settings/usage?period=12')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('period');
});

it('shows the usage to signed in users only', function () {
    $this->getJson('/settings/usage')->assertUnauthorized();
});
