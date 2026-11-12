<?php

namespace App\Ai\Enums;

use Laravel\Ai\Enums\Lab;

enum AgentEffortEnum: string
{
    case Default = 'default';

    case High = 'high';

    /**
     * Get the provider options asking the given model to think harder.
     *
     * Models rejecting these options fail the whole request, so a model that is not known to support them,
     * or a provider without such an option, runs at its own default effort.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider, string $model): array
    {
        if ($this === self::Default) {
            return [];
        }

        return match (is_string($provider) ? Lab::tryFrom($provider) : $provider) {
            Lab::Anthropic => self::supportsAdaptiveThinking($model)
                ? ['thinking' => ['type' => 'adaptive'], 'output_config' => ['effort' => 'high']]
                : [],
            Lab::Gemini => str_starts_with($model, 'gemini-3')
                ? ['generation_config' => ['thinking_level' => 'high']]
                : [],
            Lab::OpenAI => self::supportsOpenAiReasoning($model)
                ? ['reasoning' => ['effort' => 'high']]
                : [],
            Lab::OpenRouter => ['reasoning' => ['effort' => 'high']],
            default => [],
        };
    }

    /**
     * Claude models from 4.6 onwards use adaptive thinking; older ones reject it.
     */
    private static function supportsAdaptiveThinking(string $model): bool
    {
        if (! preg_match('/^claude-[a-z]+-(\d+)(?:-(\d{1,2}))?(?:-|$)/', $model, $version)) {
            return false;
        }

        return version_compare($version[1].'.'.($version[2] ?? '0'), '4.6', '>=');
    }

    private static function supportsOpenAiReasoning(string $model): bool
    {
        return (str_starts_with($model, 'gpt-5') && ! str_starts_with($model, 'gpt-5-chat'))
            || str_starts_with($model, 'gpt-6')
            || str_starts_with($model, 'o1')
            || str_starts_with($model, 'o3')
            || str_starts_with($model, 'o4');
    }
}
