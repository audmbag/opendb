<?php

namespace App\Ai\Services;

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Data\AgentSettings;
use App\Models\Project;
use App\Models\User;
use App\Repository\ConversationRepository;
use App\Repository\ProviderRepository;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\AgentInput;
use Laravel\Ai\Files\File;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;

/**
 * A turn of a conversation with the agent, the same whether it is asked from the web chat or from the console.
 */
class ConversationTurn
{
    public function __construct(
        protected ProviderRepository $providerRepository,
        protected ConversationRepository $conversationRepository,
        protected AnswerCancellation $cancellation,
    ) {}

    /**
     * Stream the answer of the agent to a message of the user, or to their decisions on the tool calls waiting for
     * approval. An answer the user stops keeps the steps taken until then, and is saved as interrupted.
     *
     * @param  int|null  $regenerateFrom  the position, from 1, of the message of the user to answer again: it is forgotten
     *                                    with everything answered after it
     * @param  list<File>  $attachments  the files joined to the message, when it is not the web chat sending them with it
     */
    public function stream(Conversation $conversation, User $user, AgentInput|Decisions|string $input, AgentSettings $settings, ?int $regenerateFrom = null, array $attachments = []): StreamableAgentResponse
    {
        $this->providerRepository->activate($settings->provider);
        $this->cancellation->forget($conversation->id);

        if ($regenerateFrom !== null) {
            $this->conversationRepository->forgetTurnsFrom($conversation, $regenerateFrom);
        }

        return new SQLDatabaseAgent($settings->accessMode, Project::firstWhere('id', $conversation->project_id), $settings->model, $settings->effort, $user)
            ->continue($conversation->id, $user)
            ->stream($input, $attachments, provider: $settings->provider->id, model: $settings->model)
            ->then(function (StreamedAgentResponse $response) use ($conversation): void {
                if ($response->assistantMessageId !== null && $this->cancellation->isRequested($conversation->id)) {
                    $this->conversationRepository->markCancelled($response->assistantMessageId);
                }

                $this->cancellation->forget($conversation->id);
            });
    }
}
