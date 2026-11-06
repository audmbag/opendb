<?php

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Middleware\LogAgentActivity;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

it('logs the input tokens of a step and those read from the cache', function () {
    $step = new PendingStep(
        number: 2,
        isFinalStep: true,
        provider: 'gemini',
        model: 'gemini-3.5-flash-lite',
        instructions: null,
        messages: [],
        tools: [],
        schema: null,
        options: null,
    );
    $stepResponse = new StepResponse(
        text: 'Bonjour !',
        toolCalls: [],
        finishReason: FinishReason::Stop,
        usage: new TextUsage(inputTokens: 1000, outputTokens: 10, cacheReadInputTokens: 600),
        meta: new Meta,
    );
    Log::shouldReceive('channel')->with('agent-logs')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context) => $message === 'Réponse agent'
        && $context['input_tokens'] === 1000
        && $context['cached_input_tokens'] === 600);

    new LogAgentActivity(AgentAccessModeEnum::ReadOnly)
        ->handle($step, fn () => new StepResult($stepResponse))
        ->response();
});
