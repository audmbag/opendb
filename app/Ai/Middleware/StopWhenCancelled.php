<?php

namespace App\Ai\Middleware;

use App\Ai\Services\AnswerCancellation;
use Closure;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;

/**
 * Stop the answer before its next step once the user stopped it, or left the page. The step already running ends,
 * so the steps taken until then are saved in the conversation.
 */
class StopWhenCancelled
{
    /**
     * @param  Closure(): ?string  $conversationId  the conversation is only known once the agent continues it
     */
    public function __construct(
        protected Closure $conversationId,
        protected AnswerCancellation $cancellation,
    ) {}

    public function handle(PendingStep $step, Closure $next): StepResult
    {
        $conversationId = ($this->conversationId)();

        if (connection_aborted() || ($conversationId !== null && $this->cancellation->isRequested($conversationId))) {
            return StepResult::stop();
        }

        return $next($step);
    }
}
