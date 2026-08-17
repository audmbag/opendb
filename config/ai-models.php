<?php

declare(strict_types=1);

use App\Ai\ModelResolver\GeminiResolver;
use App\Ai\ModelResolver\OllamaResolver;
use Laravel\Ai\Enums\Lab;
use LmSomeco\AiModels\Models\AiConnector;
use LmSomeco\AiModels\Resolvers\AnthropicResolver;
use LmSomeco\AiModels\Resolvers\OpenAiCompatibleResolver;
use LmSomeco\AiModels\Resolvers\OpenRouterResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Model lists are cached per provider. Set "ttl" to null to cache forever
    | (and bust manually via `ai:models --refresh` or AiModels::refresh()), or
    | to a number of seconds for time-based expiry. A null "store" uses the
    | application's default cache store.
    |
    */

    'cache' => [
        'store' => env('AI_MODELS_CACHE_STORE'),
        'ttl' => env('AI_MODELS_CACHE_TTL', 3600),
        'prefix' => 'ai-models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default request timeout (seconds)
    |--------------------------------------------------------------------------
    */

    'timeout' => env('AI_MODELS_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Database connectors
    |--------------------------------------------------------------------------
    |
    | When enabled, ConnectorManager will read provider credentials from the
    | ai_connectors database table instead of (or in addition to) config/ai.php
    | and .env. Set AI_MODELS_CONNECTORS=true and publish the migration:
    |
    |   php artisan vendor:publish --tag=ai-models-migrations
    |
    | Override "model" with any Eloquent model implementing the
    | LmSomeco\AiModels\Contracts\Connector contract: extend the shipped
    | AiConnector, use the LmSomeco\AiModels\Concerns\IsConnector trait when
    | your columns match the standard layout, or implement the contract's
    | getters and finders yourself for a custom schema.
    |
    */

    'connectors' => [
        'enabled' => env('AI_MODELS_CONNECTORS', false),
        'model' => AiConnector::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolvers
    |--------------------------------------------------------------------------
    |
    | Maps a laravel/ai provider (keyed by its Lab enum value) to the resolver
    | that knows how to list its models. Credentials and base URLs are read
    | from your application's config/ai.php — this package does not duplicate
    | them. The optional "url" below is only a fallback for providers that do
    | not declare one in config/ai.php.
    |
    */

    'resolvers' => [

        Lab::OpenAI->value => [
            'driver' => OpenAiCompatibleResolver::class,
        ],

        Lab::Groq->value => [
            'driver' => OpenAiCompatibleResolver::class,
            'url' => 'https://api.groq.com/openai/v1',
        ],

        Lab::Mistral->value => [
            'driver' => OpenAiCompatibleResolver::class,
            'url' => 'https://api.mistral.ai/v1',
        ],

        Lab::DeepSeek->value => [
            'driver' => OpenAiCompatibleResolver::class,
            'url' => 'https://api.deepseek.com',
        ],

        Lab::xAI->value => [
            'driver' => OpenAiCompatibleResolver::class,
            'url' => 'https://api.x.ai/v1',
        ],

        Lab::OpenRouter->value => [
            'driver' => OpenRouterResolver::class,
            'url' => 'https://openrouter.ai/api/v1',
        ],

        Lab::Anthropic->value => [
            'driver' => AnthropicResolver::class,
            'version' => '2023-06-01',
        ],

        Lab::Gemini->value => [
            'driver' => GeminiResolver::class,
            'url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],

        Lab::Ollama->value => [
            'driver' => OllamaResolver::class,
            'url' => 'https://ollama.com/api',
        ],
    ],

];
