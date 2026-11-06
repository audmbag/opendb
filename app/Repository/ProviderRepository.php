<?php

namespace App\Repository;

use App\Ai\Services\ProviderManagerService;
use App\Models\Provider;
use Illuminate\Support\Collection;

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
                    'provider' => $provider->id->value,
                    'provider_label' => $provider->label,
                    'id' => $model['id'],
                    'label' => $model['label'],
                    'context_window' => $model['context_window'] ?? null,
                ])
        )->values();
    }

    public function connect(array $data, Provider $provider): Provider
    {
        $provider_manager = new ProviderManagerService(
            provider: $provider->id,
            api_key: $data['api_key'],
            api_url: $data['api_url'] ?? null
        );

        $models = $provider_manager->getModes();

        return tap($provider)->update([
            'api_key' => $data['api_key'],
            'api_url' => $data['api_url'] ?? null,
            'models' => $models->toArray(),
        ]);
    }

    /**
     * Make the credentials stored for the provider the ones used by the AI SDK for the rest of the request.
     */
    public function activate(Provider $provider): void
    {
        ProviderManagerService::for($provider);
    }

    public function disconnect(Provider $provider): Provider
    {
        return tap($provider)->update(['api_key' => null, 'models' => null]);
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
