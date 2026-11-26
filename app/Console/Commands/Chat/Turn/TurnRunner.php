<?php

namespace App\Console\Commands\Chat\Turn;

use App\Ai\Services\ConversationTurn;
use App\Console\Commands\Chat\Approvals\ApprovalFlow;
use App\Console\Commands\Chat\Attachments;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Jobs\GenerateConversationTitle;
use App\Repository\ConversationRepository;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Files\File;
use Throwable;

/**
 * Sends a message to the agent and follows its answer to the end, through the approvals it asks on the way.
 */
class TurnRunner
{
    public function __construct(
        private readonly ConversationTurn $turn,
        private readonly ConversationRepository $conversationRepository,
        private readonly TurnPrinter $printer,
        private readonly ApprovalFlow $approvals,
        private readonly CancelHandler $cancelHandler,
        private readonly WaitIndicator $indicator,
        private readonly Terminal $terminal,
        private readonly Attachments $attachments,
    ) {}

    /**
     * @param  int|null  $regenerateFrom  the position, from 1, of the message of the user answered again
     */
    public function send(ChatSession $session, string $message, ?int $regenerateFrom = null): void
    {
        if ($session->conversation === null) {
            $session->conversation = $this->conversationRepository->start($session->project, GenerateConversationTitle::titleFromMessage($message));
            $session->awaitsTitle = true;
        }

        $position = $regenerateFrom ?? $this->userMessages($session)->count() + 1;

        $this->settle($session, $this->run($session, $message, $regenerateFrom, $this->attachments->files($session)), callsShown: true);

        // A message is saved with its answer: when the answer failed or was stopped, only the chat still knows it.
        $session->unansweredMessage = $this->userMessages($session)->count() < $position ? $message : null;

        if ($session->unansweredMessage === null) {
            $session->attachments = [];
        }

        // A first answer that failed or was stopped leaves the title to the one that comes after it.
        if ($session->awaitsTitle && $session->unansweredMessage === null) {
            $this->title($session, $this->userMessages($session)->first() ?? $message);
        }
    }

    /**
     * Ask the agent to answer the last message again. A message whose answer never came is simply sent again; the
     * answer of another one is replaced, as the web chat does.
     *
     * @return bool false when there is no message to answer again
     */
    public function retry(ChatSession $session): bool
    {
        if ($session->unansweredMessage !== null) {
            $this->send($session, $session->unansweredMessage);

            return true;
        }

        $messages = $session->conversation === null ? new Collection : $this->userMessages($session);

        if ($messages->isEmpty()) {
            return false;
        }

        $this->send($session, $messages->last(), $messages->count());

        return true;
    }

    /**
     * Ask again the approvals a conversation was left waiting for, so it can go on.
     */
    public function resume(ChatSession $session): void
    {
        if ($session->conversation !== null) {
            $this->settle($session, $this->conversationRepository->pendingApprovals($session->conversation), callsShown: false);
        }
    }

    /**
     * The agent goes on as soon as the user has decided, and may then ask for other approvals.
     *
     * @param  Collection<int, PendingApproval>  $pendingApprovals
     * @param  bool  $callsShown  false for the approvals of a conversation the user comes back to: what they ask is shown again
     */
    private function settle(ChatSession $session, Collection $pendingApprovals, bool $callsShown): void
    {
        while ($pendingApprovals->isNotEmpty()) {
            $pendingApprovals = $this->run($session, $this->approvals->decide($session, $pendingApprovals, $callsShown));
            $callsShown = true;
        }
    }

    /**
     * @param  list<File>  $attachments  the files joined to the message
     * @return Collection<int, PendingApproval> the approvals the answer stopped on
     */
    private function run(ChatSession $session, Decisions|string $input, ?int $regenerateFrom = null, array $attachments = []): Collection
    {
        $pendingApprovals = null;

        $answered = $this->cancelHandler->watch(function () use ($session, $input, $regenerateFrom, $attachments, &$pendingApprovals): void {
            $pendingApprovals = $this->printer->print(
                $this->turn->stream($session->conversation, $session->user, $input, $session->settings, $regenerateFrom, $attachments)
            )->pendingApprovals;
        });

        if (! $answered) {
            $this->terminal->notice(__('chat_cli.turn.aborted'));

            return new Collection;
        }

        // The answer may have been written by another process, which could only leave its approvals in the conversation.
        return $pendingApprovals ?? $this->conversationRepository->pendingApprovals($session->conversation);
    }

    /**
     * @return Collection<int, string> the messages of the user saved in the conversation, the oldest first
     */
    private function userMessages(ChatSession $session): Collection
    {
        return $session->conversation->messages()->where('role', 'user')->oldest()->orderBy('id')->pluck('content');
    }

    /**
     * The console has no queue worker, so the title is written here, once the first answer is read.
     *
     * @param  string  $message  the first message of the conversation, which the title sums up
     */
    private function title(ChatSession $session, string $message): void
    {
        $this->indicator->start(__('conversations.generating_title'));

        try {
            GenerateConversationTitle::dispatchSync($session->conversation, $message, $session->settings->provider->id, $session->settings->model);
        } catch (Throwable) {
            // The conversation keeps its first message as a title.
        } finally {
            $this->indicator->stop();
        }

        $session->awaitsTitle = false;

        $session->conversation->refresh();

        $this->terminal->notice(__('chat_cli.turn.titled', ['title' => $session->conversation->title]));
    }
}
