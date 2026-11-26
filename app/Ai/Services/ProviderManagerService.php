<?php

namespace App\Ai\Services;

use App\Ai\Data\AiModel;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Ai;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

readonly class ProviderManagerService
{
    /**
     * @param  string  $name  the name the provider is configured under in config('ai.providers')
     * @param  string  $api_key  empty for a provider answering without a key, like a local Ollama
     */
    public function __construct(
        private string $name,
        private Lab $driver,
        private string $api_key,
        private ?string $api_url = null
    ) {
        $this->setConfig();
    }

    /**
     * Use the credentials stored for the provider. Without a stored URL, the SDK uses the default one of the provider.
     */
    public static function for(Provider $provider, ?string $api_key = null, ?string $api_url = null): self
    {
        return new self(
            $provider->name(),
            $provider->driver(),
            $api_key ?? (string) $provider->api_key,
            $api_url ?? $provider->api_url,
        );
    }

    private function setConfig(): void
    {
        Config::set([
            "ai.providers.{$this->name}" => [...[
                'driver' => $this->driver->value,
                'key' => $this->api_key,
            ], ...($this->api_url ? ['url' => $this->api_url] : [])],
            'ai.default' => $this->name,
            'ai.default_for_images' => $this->name,
            'ai.default_for_audio' => $this->name,
            'ai.default_for_transcription' => $this->name,
            'ai.default_for_embeddings' => $this->name,
            'ai.default_for_reranking' => $this->name,
        ]);

        Ai::forgetInstance($this->name);
    }

    public function getModes(): Collection
    {
        $ai_models = app(ModelRegistry::class)->provider($this->name);

        return $ai_models->map(function (AiModel $model) {
            return [
                'id' => $model->id,
                'label' => $model->name,
                'context_window' => $model->contextWindow,
                // What the model reads besides text, when the provider tells it: an empty list is not known.
                'modalities' => $model->modalities,
                'active' => false,
            ];
        });
    }

    /**
     * The answer of the model as it wrote it: the caller cleans it up and falls back on the message when it fails.
     */
    public function generateConversationTitle(string $prompt, string $model): string
    {
        $instructions = 'Generate a concise 3-5 word title for a conversation that starts with the given message. Use the same language as the message. Respond with only the title, no quotes or punctuation.';

        return agent(instructions: $instructions)->prompt(<<<PROMPT
            The message :
            ___
            {$prompt}
            ___
        PROMPT, provider: $this->name, model: $model)->text;
    }
}
