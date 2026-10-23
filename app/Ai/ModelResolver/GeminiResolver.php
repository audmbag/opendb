<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use Illuminate\Support\Collection;
use LmSomeco\AiModels\Data\AiModel;
use LmSomeco\AiModels\Resolvers\Resolver;

class GeminiResolver extends Resolver
{
    protected function fetch(): Collection
    {
        $models = collect();
        $query = ['pageSize' => 1000];

        do {
            $response = $this->request()
                ->withHeaders(['x-goog-api-key' => (string) $this->apiKey()])
                ->get('/models', $query)
                ->throw();

            foreach ($response->json('models', []) as $model) {
                if ($mapped = $this->mapModel($model)) {
                    $models->push($mapped);
                }
            }

            $query['pageToken'] = $response->json('nextPageToken');
        } while (filled($query['pageToken'] ?? null));

        return $models->sortBy('id')->values();
    }

    /**
     * @param  array<string, mixed>  $model
     */
    protected function mapModel(array $model): ?AiModel
    {
        if (empty($model['name'])) {
            return null;
        }

        $id = str_starts_with((string) $model['name'], 'models/')
            ? substr((string) $model['name'], strlen('models/'))
            : (string) $model['name'];

        return $this->newModel(
            id: $id,
            name: $model['displayName'] ?? $id,
            contextWindow: isset($model['inputTokenLimit']) ? (int) $model['inputTokenLimit'] : null,
            maxOutputTokens: isset($model['outputTokenLimit']) ? (int) $model['outputTokenLimit'] : null,
            raw: $model,
        );
    }
}
