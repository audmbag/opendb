<?php

declare(strict_types=1);

namespace App\Services\Disconnection;

use App\Ai\Services\AnswerCancellation;
use App\Ai\Services\AnswersInProgress;
use App\Models\Project;
use App\Models\User;

/**
 * The answers the agent still gives the user in the project, stopped before their next step. The page may have
 * stopped reading them long before, when the user went to another page.
 */
final readonly class AnswerStopper
{
    public function __construct(
        private AnswersInProgress $answers,
        private AnswerCancellation $cancellation,
    ) {}

    public function stop(Project $project, User $user): void
    {
        $answering = $user->conversations()
            ->where('project_id', $project->id)
            ->pluck('id')
            ->all()
            |> $this->answers->among(...);

        foreach ($answering as $conversationId) {
            $this->cancellation->request($conversationId);
        }
    }
}
