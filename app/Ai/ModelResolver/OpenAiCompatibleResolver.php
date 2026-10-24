<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use App\Ai\Data\AiModel;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

/**
 * Gère tout provider exposant un endpoint `GET /models` au format OpenAI
 * (`{ "data": [ { "id": "..." } ] }`) avec une authentification Bearer :
 * OpenAI, Groq, Mistral, DeepSeek et xAI ne diffèrent que par leur URL de base.
 */
class OpenAiCompatibleResolver extends Resolver
{
    protected function fetch(): Collection
    {
        $data = $this->request()->get('/models')->throw()->json('data', []);

        return collect(is_array($data) ? $data : [])
            ->map(fn (array $model): ?AiModel => $this->mapModel($model))
            ->filter()
            ->sortBy('id')
            ->values();
    }

    protected function request(): PendingRequest
    {
        $request = parent::request();

        if (filled($this->apiKey())) {
            $request->withToken((string) $this->apiKey());
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $model
     */
    protected function mapModel(array $model): ?AiModel
    {
        if (empty($model['id'])) {
            return null;
        }

        return $this->newModel(
            id: (string) $model['id'],
            name: (string) $model['id'],
            createdAt: isset($model['created'])
                ? (new DateTimeImmutable)->setTimestamp((int) $model['created'])
                : null,
            raw: $model,
        );
    }
}
