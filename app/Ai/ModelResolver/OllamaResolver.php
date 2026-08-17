<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use DateTimeImmutable;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use LmSomeco\AiModels\Contracts\ProviderResolver;
use LmSomeco\AiModels\Data\AiModel;

class OllamaResolver implements ProviderResolver
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
        return filled($this->baseUrl());
    }

    public function models(): Collection
    {
        $request = $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15));

        if (filled($this->config['key'] ?? null)) {
            $request->withToken((string) $this->config['key']);
        }

        $response = $request->get('/api/tags')->throw();

        return collect($response->json('models', []))
            ->map(fn (array $model): ?AiModel => $this->mapModel($model))
            ->filter()
            ->sortBy('id')
            ->values();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['url'] ?? 'http://localhost:11434'), '/');
    }

    /**
     * @param  array<string, mixed>  $model
     */
    protected function mapModel(array $model): ?AiModel
    {
        $id = $model['model'] ?? $model['name'] ?? null;

        if (empty($id)) {
            return null;
        }

        return new AiModel(
            provider: $this->provider(),
            id: (string) $id,
            name: (string) ($model['name'] ?? $id),
            createdAt: $this->parseModifiedAt($model['modified_at'] ?? null),
            raw: $model,
        );
    }

    protected function parseModifiedAt(?string $modifiedAt): ?DateTimeImmutable
    {
        if (blank($modifiedAt)) {
            return null;
        }

        try {
            return new DateTimeImmutable($modifiedAt);
        } catch (Exception) {
            return null;
        }
    }
}
