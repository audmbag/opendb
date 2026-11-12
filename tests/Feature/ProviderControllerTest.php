<?php

use App\Enums\PrivilegeEnum;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customProviderForm(array $overrides = []): array
{
    return [
        'id' => 'my-llm',
        'label' => 'My LLM',
        'api_url' => 'https://llm.example.test/v1',
        'api_key' => 'sk-custom',
        'models' => json_encode([
            ['id' => 'row-1', 'key' => 'llama-4', 'value' => 'Llama 4'],
            ['id' => 'row-2', 'key' => 'qwen-3', 'value' => ''],
            ['id' => 'row-3', 'key' => '', 'value' => ''],
        ]),
        ...$overrides,
    ];
}

it('adds a custom provider with the models the user listed, all active', function () {
    $user = User::factory()->withPrivileges(PrivilegeEnum::ManageProviders)->create();

    $response = $this->actingAs($user)->post('/providers', customProviderForm());

    $response->assertRedirect()->assertSessionHasNoErrors();
    $provider = Provider::find('my-llm');
    expect($provider->is_connected)->toBeTrue()
        ->and($provider->custom_openai_compatible)->toBeTrue()
        ->and($provider->api_key)->toBe('sk-custom')
        ->and($provider->icon)->toBe('/assets/providers/custom.svg')
        ->and(collect($provider->models)->map(fn (array $model): array => array_intersect_key($model, array_flip(['id', 'label', 'active'])))->all())->toBe([
            ['id' => 'llama-4', 'label' => 'Llama 4', 'active' => true],
            ['id' => 'qwen-3', 'label' => 'qwen-3', 'active' => true],
        ]);
});

it('rejects a custom provider it could not configure', function (array $overrides, string $invalidField) {
    $user = User::factory()->withPrivileges(PrivilegeEnum::ManageProviders)->create();
    Provider::query()->updateOrCreate(['id' => 'ollama'], ['label' => 'Ollama']);

    $response = $this->actingAs($user)->post('/providers', customProviderForm($overrides));

    $response->assertSessionHasErrors($invalidField);
    expect(Provider::find('my-llm'))->toBeNull();
})->with([
    'id of a laravel/ai driver' => [['id' => 'openai-compatible'], 'id'],
    'id of a default provider' => [['id' => 'ollama'], 'id'],
    'id with spaces' => [['id' => 'My LLM'], 'id'],
    'label of another provider' => [['label' => 'Ollama'], 'label'],
    'no base URL' => [['api_url' => ''], 'api_url'],
    'no API key' => [['api_key' => ''], 'api_key'],
    'no model' => [['models' => json_encode([['id' => 'row-1', 'key' => '', 'value' => 'Llama 4']])], 'models'],
]);

it('connects Ollama without an API key', function () {
    $user = User::factory()->withPrivileges(PrivilegeEnum::ManageProviders)->create();
    Provider::query()->updateOrCreate(['id' => 'ollama'], ['label' => 'Ollama', 'api_key' => null, 'models' => null]);
    Http::fake(['localhost:11434/api/tags' => Http::response(['models' => [['name' => 'llama3:latest', 'model' => 'llama3:latest']]])]);

    $response = $this->actingAs($user)->patch('/providers/ollama', ['api_key' => '', 'api_url' => 'http://localhost:11434']);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $provider = Provider::find('ollama');
    expect($provider->is_connected)->toBeTrue()
        ->and($provider->api_key)->toBe('')
        ->and(collect($provider->models)->pluck('id')->all())->toBe(['llama3:latest']);
});

it('still asks the other providers for their API key', function () {
    $user = User::factory()->withPrivileges(PrivilegeEnum::ManageProviders)->create();
    Provider::query()->updateOrCreate(['id' => 'openai'], ['label' => 'Openai', 'api_key' => null, 'models' => null]);
    Http::fake();

    $response = $this->actingAs($user)->patch('/providers/openai', ['api_key' => '']);

    $response->assertSessionHasErrors('api_key');
    Http::assertNothingSent();
});

it('removes a custom provider once disconnected, but keeps a default one', function () {
    $user = User::factory()->withPrivileges(PrivilegeEnum::ManageProviders)->create();
    $this->actingAs($user)->post('/providers', customProviderForm());
    Provider::query()->updateOrCreate(['id' => 'openai'], ['label' => 'Openai', 'api_key' => 'sk-openai', 'models' => [['id' => 'gpt-5.1', 'label' => 'GPT 5.1', 'active' => true]]]);

    $this->actingAs($user)->deleteJson('/providers/my-llm')->assertOk();
    $this->actingAs($user)->deleteJson('/providers/openai')->assertOk();

    expect(Provider::find('my-llm'))->toBeNull()
        ->and(Provider::find('openai')->is_connected)->toBeFalse();
});

it('refuses to change the AI providers without MANAGE PROVIDERS', function () {
    $this->actingAs(User::factory()->create())->post('/providers', customProviderForm())->assertForbidden();

    expect(Provider::find('my-llm'))->toBeNull();
});
