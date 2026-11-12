<?php

namespace App\Ai\Middleware;

use App\Ai\Enums\AgentAccessModeEnum;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;

class LogAgentActivity
{
    public function __construct(
        public AgentAccessModeEnum $accessMode,
    ) {}

    /**
     * Log the user prompt on the first generation step, then the response of every step.
     */
    public function handle(PendingStep $step, Closure $next): StepResult
    {
        $prompt = $step->isFirstStep() ? last($step->messages) : null;

        if ($prompt instanceof UserMessage) {
            Log::channel('agent-logs')->info('Prompt agent', [
                'mode' => $this->accessMode->value,
                'prompt' => $prompt->content,
            ]);
        }

        return $next($step)->then(function (StepResponse $response) use ($step) {
            Log::channel('agent-logs')->info('Agent answer', [
                'mode' => $this->accessMode->value,
                'step' => $step->number,
                'text' => $response->text,
                'pending_approvals' => $response->pendingApprovals !== [],
                'input_tokens' => $response->usage->inputTokens,
                'cached_input_tokens' => $response->usage->cacheReadInputTokens,
            ]);
        });
    }
}
