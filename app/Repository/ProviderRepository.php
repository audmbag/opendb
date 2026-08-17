<?php

namespace App\Repository;

use App\Models\Provider;
use Illuminate\Support\Collection;
use LmSomeco\AiModels\Data\AiModel;
use LmSomeco\AiModels\Facades\AiModels;

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
        $models = $this->getModels($provider);

        return tap($provider)->update([
            'api_key' => $data['api_key'],
            'api_url' => $data['api_url'] ?? null,
            'models' => $models,
        ]);
    }


    protected function getModels(Provider $provider): Collection
    {
        $ai_models = AiModels::provider($provider->id);

        return $ai_models->map(function (AiModel $model) {
            return [
                'id' => $model->id,
                'label' => $model->name,
                'context_window' => $model->contextWindow,
                'enabled' => false,
            ];
        });
    }


    public function disconnect(Provider $provider): Provider
    {
        return tap($provider)->update(['api_key' => null, 'models' => null]);
    }
}
