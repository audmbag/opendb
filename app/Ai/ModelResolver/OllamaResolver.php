<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use App\Ai\Data\AiModel;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Collection;

class OllamaResolver extends Resolver
{
    protected function fetch(): Collection
    {
        $request = $this->request();

        if (filled($this->apiKey())) {
            $request->withToken((string) $this->apiKey());
        }

        $response = $request->get('/api/tags')->throw();

        return collect($response->json('models', []))
            ->map(fn (array $model): ?AiModel => $this->mapModel($model))
            ->filter()
            ->sortBy('id')
            ->values();
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

        return $this->newModel(
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
