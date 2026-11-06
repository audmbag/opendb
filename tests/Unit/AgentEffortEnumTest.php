<?php

use App\Ai\Enums\AgentEffortEnum;
use Laravel\Ai\Enums\Lab;

it('adds no provider option at the default effort', function () {
    expect(AgentEffortEnum::Default->providerOptions(Lab::Anthropic, 'claude-opus-5-5'))->toBe([]);
});

it('asks the models supporting it for a high effort', function (Lab $provider, string $model, array $options) {
    expect(AgentEffortEnum::High->providerOptions($provider, $model))->toBe($options);
})->with([
    'Claude 4.6' => [Lab::Anthropic, 'claude-sonnet-4-6', ['thinking' => ['type' => 'adaptive'], 'output_config' => ['effort' => 'high']]],
    'Claude 5.5' => [Lab::Anthropic, 'claude-opus-5-5', ['thinking' => ['type' => 'adaptive'], 'output_config' => ['effort' => 'high']]],
    'Claude 5' => [Lab::Anthropic, 'claude-fable-5', ['thinking' => ['type' => 'adaptive'], 'output_config' => ['effort' => 'high']]],
    'Gemini 3' => [Lab::Gemini, 'gemini-3.5-flash-lite', ['generation_config' => ['thinking_level' => 'high']]],
    'OpenAI reasoning model' => [Lab::OpenAI, 'gpt-5.1', ['reasoning' => ['effort' => 'high']]],
    'OpenRouter' => [Lab::OpenRouter, 'any/model', ['reasoning' => ['effort' => 'high']]],
]);

it('leaves the models that would reject a high effort at their default', function (Lab $provider, string $model) {
    expect(AgentEffortEnum::High->providerOptions($provider, $model))->toBe([]);
})->with([
    'dated Claude 4.5' => [Lab::Anthropic, 'claude-sonnet-4-5-20250929'],
    'dated Claude 4' => [Lab::Anthropic, 'claude-opus-4-20250514'],
    'Claude 3' => [Lab::Anthropic, 'claude-3-5-sonnet-latest'],
    'Gemini 2.5' => [Lab::Gemini, 'gemini-2.5-pro'],
    'OpenAI without reasoning' => [Lab::OpenAI, 'gpt-4.1'],
    'OpenAI chat model' => [Lab::OpenAI, 'gpt-5-chat-latest'],
    'provider without the option' => [Lab::Mistral, 'mistral-large-latest'],
]);
