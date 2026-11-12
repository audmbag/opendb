<?php

namespace App\Listeners;

use App\Ai\Enums\AiFeatureEnum;
use App\Models\UsageRecord;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\StepFailed;

/**
 * Record every call made to a model, by any agent of the application, for the usage settings.
 */
class RecordStepUsage
{
    public function handle(StepCompleted|StepFailed $event): void
    {
        $usage = $event instanceof StepCompleted ? $event->response->usage : null;

        UsageRecord::query()->create([
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'feature' => AiFeatureEnum::fromAgent($event->agent),
            'invocation_id' => $event->invocationId,
            'input_tokens' => $usage?->inputTokens ?? 0,
            'output_tokens' => $usage?->outputTokens ?? 0,
            'cache_read_input_tokens' => $usage?->cacheReadInputTokens ?? 0,
            'cache_write_input_tokens' => $usage?->cacheWriteInputTokens ?? 0,
            'reasoning_tokens' => $usage?->reasoningTokens ?? 0,
            'duration' => (int) round($event->time),
            'failed' => $event instanceof StepFailed,
            'created_at' => now(),
        ]);
    }
}
