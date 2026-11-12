<?php

return [

    'default' => null,
    'default_for_images' => null,
    'default_for_audio' => null,
    'default_for_transcription' => null,
    'default_for_embeddings' => null,
    'default_for_reranking' => null,

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'openai' => [
            'driver' => 'openai',
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
        ],

        'xai' => [
            'driver' => 'xai',
        ],

        'groq' => [
            'driver' => 'groq',
        ],

        'mistral' => [
            'driver' => 'mistral',
        ],

        'openrouter' => [
            'driver' => 'openrouter',
        ],

        'ollama' => [
            'driver' => 'ollama',
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        // 'openai-compatible' => [
        //     'driver' => 'openai-compatible',
        //     'url' => env('OPENAI_COMPATIBLE_URL'),
        //     'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        // ],
    ],

];
