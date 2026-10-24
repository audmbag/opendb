<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use App\Ai\Data\AiModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Anthropic expose `GET /v1/models` avec une authentification par en-têtes `x-api-key` et
 * `anthropic-version`, et pagine ses résultats avec `after_id` / `has_more`.
 */
class AnthropicResolver extends Resolver
{
    protected function fetch(): Collection
    {
        /** @var Collection<int, AiModel> $models */
        $models = collect();
        $query = ['limit' => 1000];

        do {
            $response = $this->request()
                ->withHeaders([
                    'x-api-key' => (string) $this->apiKey(),
                    'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
                ])
                ->get('/models', $query)
                ->throw();

            foreach ($response->json('data', []) as $model) {
                if (empty($model['id'])) {
                    continue;
                }

                $models->push($this->newModel(
                    id: (string) $model['id'],
                    name: $model['display_name'] ?? (string) $model['id'],
                    contextWindow: isset($model['max_input_tokens']) ? (int) $model['max_input_tokens'] : null,
                    maxOutputTokens: isset($model['max_tokens']) ? (int) $model['max_tokens'] : null,
                    modalities: ($model['capabilities']['image_input']['supported'] ?? null) === false ? ['text'] : ['text', 'image'],
                    createdAt: isset($model['created_at']) ? new DateTimeImmutable((string) $model['created_at']) : null,
                    raw: $model,
                    outputModalities: ['text'],
                ));
            }

            $hasMore = (bool) $response->json('has_more', false);
            $query['after_id'] = $response->json('last_id');
        } while ($hasMore && filled($query['after_id']));

        return $models->sortBy('id')->values();
    }
}
