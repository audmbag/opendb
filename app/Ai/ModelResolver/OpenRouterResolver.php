<?php

declare(strict_types=1);

namespace App\Ai\ModelResolver;

use App\Ai\Data\AiModel;

/**
 * OpenRouter liste ses modèles au format OpenAI, mais avec des métadonnées plus riches (taille du
 * contexte, modalités) et sans exiger de clé d'API pour cette liste.
 */
class OpenRouterResolver extends OpenAiCompatibleResolver
{
    public function configured(): bool
    {
        // L'endpoint /models est public : la clé n'est nécessaire que pour l'inférence.
        return true;
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
            name: $model['name'] ?? (string) $model['id'],
            contextWindow: isset($model['context_length']) ? (int) $model['context_length'] : null,
            maxOutputTokens: isset($model['top_provider']['max_completion_tokens'])
                ? (int) $model['top_provider']['max_completion_tokens']
                : null,
            modalities: $model['architecture']['input_modalities'] ?? [],
            raw: $model,
            outputModalities: $model['architecture']['output_modalities'] ?? [],
        );
    }
}
