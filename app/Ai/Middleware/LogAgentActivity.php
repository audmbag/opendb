<?php

namespace App\Ai\Middleware;

use App\Ai\Enums\AgentAccessModeEnum;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

class LogAgentActivity
{
    public function __construct(
        public AgentAccessModeEnum $accessMode,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        Log::channel('agent-logs')->info('Prompt agent', [
            'mode' => $this->accessMode->value,
            'prompt' => $prompt->prompt,
        ]);

        return $next($prompt)->then(function (AgentResponse $response) {
            Log::channel('agent-logs')->info('Réponse agent', [
                'mode' => $this->accessMode->value,
                'text' => $response->text ?? null,
                'pending_approvals' => method_exists($response, 'hasPendingApprovals')
                    && $response->hasPendingApprovals(),
            ]);
        });

    }
}
