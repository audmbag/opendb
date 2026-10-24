<?php

use App\Ai\Data\AiModel;
use App\Ai\Services\ModelRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\ProviderOverloadedException;

beforeEach(function () {
    config(['ai-models.cache.store' => 'array']);
    config(['ai.providers' => [
        'gemini' => ['driver' => 'gemini', 'key' => 'gemini-key'],
        'ollama' => ['driver' => 'ollama', 'url' => 'http://localhost:11434'],
        'bedrock' => ['driver' => 'bedrock', 'key' => 'bedrock-key'],
    ]]);
});

function geminiModelsResponse(): array
{
    return [
        'models' => [[
            'name' => 'models/gemini-3.5-flash-lite',
            'displayName' => 'Gemini 3.5 Flash Lite',
            'inputTokenLimit' => 1048576,
            'outputTokenLimit' => 65536,
        ]],
    ];
}

it('lists the models of a provider normalized from its API response', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiModelsResponse())]);

    $models = app(ModelRegistry::class)->provider(Lab::Gemini);

    expect($models)->toHaveCount(1)
        ->and($models->first())->toBeInstanceOf(AiModel::class)
        ->and($models->first()->toArray())->toMatchArray([
            'provider' => 'gemini',
            'driver' => 'gemini',
            'id' => 'gemini-3.5-flash-lite',
            'name' => 'Gemini 3.5 Flash Lite',
            'context_window' => 1048576,
            'max_output_tokens' => 65536,
        ]);
    Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'gemini-key'));
});

it('serves the models from the cache until a fresh list is requested', function () {
    config(['cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiModelsResponse())]);
    $registry = app(ModelRegistry::class);

    $registry->provider('gemini');
    $cachedModels = $registry->provider('gemini');
    Http::assertSentCount(1);
    expect($cachedModels->first()->toArray())->toMatchArray(['id' => 'gemini-3.5-flash-lite', 'context_window' => 1048576]);

    $registry->provider('gemini', fresh: true);
    Http::assertSentCount(2);
});

it('returns no models without calling a provider that lacks its API key', function () {
    config(['ai.providers.gemini.key' => null]);
    Http::fake();

    expect(app(ModelRegistry::class)->provider('gemini'))->toBeEmpty();
    Http::assertNothingSent();
});

it('lists the models of a provider that does not require an API key', function () {
    Http::fake(['localhost:11434/api/tags' => Http::response(['models' => [['name' => 'llama3:latest', 'model' => 'llama3:latest']]])]);

    expect(app(ModelRegistry::class)->provider('ollama')->pluck('id')->all())->toBe(['llama3:latest']);
});

it('only lists the providers that have a resolver and their credentials', function () {
    expect(app(ModelRegistry::class)->configuredProviders()->all())->toBe(['gemini', 'ollama']);
});

it('rejects a provider that is not declared or has no resolver', function (string $provider, string $message) {
    expect(fn () => app(ModelRegistry::class)->provider($provider))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'not declared' => ['openai', "No provider named [openai] configured in config('ai.providers')."],
    'without resolver' => ['bedrock', 'No model resolver registered for provider [bedrock] (driver: bedrock).'],
]);

it('rethrows an unavailable provider as a failover exception of the SDK', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

    expect(fn () => app(ModelRegistry::class)->provider('gemini'))->toThrow(ProviderOverloadedException::class);
});
