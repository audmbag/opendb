<?php

namespace App\Ai\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use LmSomeco\AiModels\Data\AiModel;
use LmSomeco\AiModels\Facades\AiModels;
use Throwable;

use function Laravel\Ai\agent;

readonly class ProviderManagerService
{
    public function __construct(
        private Lab $provider,
        private string $api_key,
        private ?string $api_url = null
    ) {
        $this->setConfig();
    }

    private function setConfig(): void
    {
        Config::set([
            "ai.providers.{$this->provider->value}" => [...[
                'driver' => $this->provider->value,
                'key' => $this->api_key,
            ], ...($this->api_url ? ['url' => $this->api_url] : [])],
            'ai.default' => $this->provider->value,
            'ai.default_for_images' => $this->provider->value,
            'ai.default_for_audio' => $this->provider->value,
            'ai.default_for_transcription' => $this->provider->value,
            'ai.default_for_embeddings' => $this->provider->value,
            'ai.default_for_reranking' => $this->provider->value,
        ]);
    }

    public function getModes(): Collection
    {
        $ai_models = AiModels::provider($this->provider);

        return $ai_models->map(function (AiModel $model) {
            return [
                'id' => $model->id,
                'label' => $model->name,
                'context_window' => $model->contextWindow,
                'active' => false,
            ];
        });
    }

    public static function generateConversationTitle(string $prompt): string
    {
        try {
            $instructions = 'Generate a concise 3-5 word title for a conversation that starts with the given message. Use the same language as the message. Respond with only the title, no quotes or punctuation.';

            $response = agent(instructions: $instructions)->prompt(<<<PROMPT
                The message :
                ___
                {$prompt}
                ___
            PROMPT);

            return Str::limit($response->text, 100);

        } catch (Throwable) {
            return Str::limit($prompt, 100, preserveWords: true);
        }
    }
}
