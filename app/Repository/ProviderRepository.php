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
