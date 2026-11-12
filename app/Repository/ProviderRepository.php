<?php

namespace App\Repository;

use App\Ai\Services\ProviderManagerService;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;

class ProviderRepository
{
    public function get(): Collection
    {
        return Provider::all();
    }

    public function connectedProviders(): Collection
    {
        return Provider::connected()->get();
    }

    public function disconnectedProviders(): Collection
    {
        return Provider::disconnected()->get();
    }

    /**
     * The models the user activated on the connected providers.
     *
     * @return Collection<int, array{provider: string, provider_label: string, id: string, label: string, context_window: int|null}>
     */
    public function activeModels(): Collection
    {
        return $this->connectedProviders()->flatMap(
            fn (Provider $provider) => collect($provider->models ?? [])
                ->where('active', true)
                ->map(fn (array $model): array => [
                    'provider' => $provider->name(),
                    'provider_label' => $provider->label,
                    'id' => $model['id'],
                    'label' => $model['label'],
                    'context_window' => $model['context_window'] ?? null,
                ])
        )->values();
    }

    /**
     * The key is checked by listing the models of the provider, which are saved with it. A custom provider keeps
     * the models the user listed. A provider answering without a key, like a local Ollama, keeps an empty one:
     * a stored key, even empty, is what marks the provider as connected.
     *
     * @param  array{api_key?: string|null, api_url?: string|null}  $data
     */
    public function connect(array $data, Provider $provider): Provider
    {
        $apiKey = $data['api_key'] ?? '';
        $apiUrl = $data['api_url'] ?? $provider->api_url;

        $models = $provider->id instanceof Lab
            ? ProviderManagerService::for($provider, $apiKey, $apiUrl)->getModes()->toArray()
            : $provider->models;

        return tap($provider)->update([
            'api_key' => $apiKey,
            'api_url' => $apiUrl,
            'models' => $models,
        ]);
    }

    /**
     * Add a provider speaking the OpenAI API, with the models the user listed: they are all active, since the
     * user chose each one of them.
     *
     * @param  array{id: string, label: string, api_url: string, api_key: string, models: list<array{id: string, label: string|null}>}  $data
     */
    public function createCustom(array $data): Provider
    {
        return Provider::query()->create([
            'id' => $data['id'],
            'label' => $data['label'],
            'api_url' => $data['api_url'],
            'api_key' => $data['api_key'],
            'custom_openai_compatible' => true,
            'models' => array_map(fn (array $model): array => [
                'id' => $model['id'],
                'label' => filled($model['label'] ?? null) ? $model['label'] : $model['id'],
                'context_window' => null,
                'modalities' => [],
                'active' => true,
            ], $data['models']),
        ]);
    }

    /**
     * Make the credentials stored for the provider the ones used by the AI SDK for the rest of the request.
     */
    public function activate(Provider $provider): void
    {
        ProviderManagerService::for($provider);
    }

    /**
     * A custom provider is removed: its models were only known from the user, who adds it again to reconnect it.
     */
    public function disconnect(Provider $provider): Provider
    {
        if (! $provider->id instanceof Lab) {
            return tap($provider)->delete();
        }

        return tap($provider)->update(['api_key' => null, 'models' => null]);
    }

    /**
     * Make the given models the only active ones of the provider.
     *
     * @param  list<string>  $modelIds
     */
    public function activateModels(Provider $provider, array $modelIds): Provider
    {
        return tap($provider)->update([
            'models' => array_map(
                fn (array $model): array => [...$model, 'active' => in_array($model['id'], $modelIds, true)],
                $provider->models ?? [],
            ),
        ]);
    }

    public function toggleModel(Provider $provider, string $modelId): bool
    {
        $models = $provider->models ?? [];

        foreach ($models as &$model) {
            if ($model['id'] === $modelId) {
                $model['active'] = ! $model['active'];
                break;
            }
        }

        $provider->update(['models' => $models]);

        return (bool) $model['active'] ?? false;
    }
}
