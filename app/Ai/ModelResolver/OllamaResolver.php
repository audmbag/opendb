<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use LmSomeco\AiModels\Contracts\ProviderResolver;
use LmSomeco\AiModels\Data\AiModel;

class GeminiResolver implements ProviderResolver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected HttpFactory $http,
        protected Lab $lab,
        protected array $config,
    ) {}

    public function provider(): Lab
    {
        return $this->lab;
    }

    public function configured(): bool
    {
        return filled($this->apiKey());
    }

    public function models(): Collection
    {
        $models = collect();
        $query = ['pageSize' => 1000];

        do {
            $response = $this->http
                ->baseUrl($this->baseUrl())
                ->withHeaders(['x-goog-api-key' => (string) $this->apiKey()])
                ->acceptJson()
                ->timeout((int) ($this->config['timeout'] ?? 15))
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

    protected function apiKey(): ?string
    {
        return $this->config['key'] ?? null;
    }

    protected function baseUrl(): string
    {
        return rtrim(
            (string) ($this->config['url'] ?? 'https://generativelanguage.googleapis.com/v1beta'),
            '/'
        );
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

        return new AiModel(
            provider: $this->provider(),
            id: $id,
            name: $model['displayName'] ?? $id,
            contextWindow: isset($model['inputTokenLimit']) ? (int) $model['inputTokenLimit'] : null,
            maxOutputTokens: isset($model['outputTokenLimit']) ? (int) $model['outputTokenLimit'] : null,
            raw: $model,
        );
    }
}
