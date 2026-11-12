<?php

namespace App\Repository;

use App\Jobs\GenerateConversationTitle;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Vercel\Vercel;

class ConversationRepository
{
    public function __construct(
        #[CurrentUser]
        public User $user
    ) {}

    /**
     * Start a conversation without a title: it is generated in the queue, so the conversation opens at once.
     */
    public function create(Project $project, string $message, Provider $provider, string $model): Conversation
    {
        $conversation = $this->start($project);

        GenerateConversationTitle::dispatch($conversation, $message, $provider->id, $model);

        return $conversation;
    }

    /**
     * Start a conversation whose title is left to the caller, like the console, which has no queue worker to write it.
     */
    public function start(Project $project): Conversation
    {
        return $this->user->conversations()->create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
        ]);
    }

    public function get(Project $project): Collection
    {
        return $this->user->conversations()
            ->where('project_id', $project->id)
            ->where('is_archived', false)
            ->latest()
            ->get();
    }

    /**
     * Find the user's conversations in the project whose title or messages contain the search,
     * each with an excerpt of the first message around it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function search(Project $project, string $search, int $limit = 20): Collection
    {
        $pattern = "%{$search}%";

        return $this->user->conversations()
            ->where('project_id', $project->id)
            ->where('is_archived', false)
            ->where(fn (Builder $query) => $query
                ->whereLike('title', $pattern)
                ->orWhereHas('messages', fn (Builder $query) => $query->whereLike('content', $pattern)))
            ->with(['messages' => fn (HasMany $query) => $query
                ->select(['id', 'conversation_id', 'content'])
                ->whereLike('content', $pattern)
                ->oldest()])
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Conversation $conversation) => [
                ...$conversation->withoutRelations()->toArray(),
                'excerpt' => Str::excerpt($conversation->messages->first()->content ?? '', $search, ['radius' => 60]),
            ]);
    }

    public function archive(Conversation $conversation): Conversation
    {
        return tap($conversation)->update(['is_archived' => true]);
    }

    /**
     * The conversations of the user in the project that they archived, the last archived first.
     *
     * @return Collection<int, Conversation>
     */
    public function archived(Project $project): Collection
    {
        return $this->archivedQuery($project)
            ->withCount('messages')
            ->latest('updated_at')
            ->get();
    }

    public function restore(Conversation $conversation): Conversation
    {
        return tap($conversation)->update(['is_archived' => false]);
    }

    /**
     * Delete every conversation of the user archived in the project, with their messages.
     */
    public function deleteArchived(Project $project): int
    {
        $conversationIds = $this->archivedQuery($project)->pluck('id');

        ConversationMessage::query()->whereIn('conversation_id', $conversationIds)->delete();

        return Conversation::query()->whereIn('id', $conversationIds)->delete();
    }

    /**
     * Forget the given message of the user and everything answered after it, so the agent can answer it again.
     * A message the server has not saved yet, like one whose answer was stopped a moment ago, leaves nothing to forget.
     *
     * @param  int  $userMessageNumber  the position of the message among those of the user, from 1
     */
    public function forgetTurnsFrom(Conversation $conversation, int $userMessageNumber): void
    {
        $messages = $conversation->messages()->oldest()->orderBy('id')->get(['id', 'role']);

        $userMessage = $messages->where('role', 'user')->values()->get($userMessageNumber - 1);

        if ($userMessage === null) {
            return;
        }

        $messages
            ->skipUntil(fn (ConversationMessage $message) => $message->id === $userMessage->id)
            ->pluck('id')
            |> (fn (Collection $ids) => $conversation->messages()->whereIn('id', $ids)->delete());
    }

    /**
     * Note that the user stopped the given answer, so the conversation shows it as interrupted.
     */
    public function markCancelled(string $messageId): void
    {
        $message = ConversationMessage::query()->find($messageId);

        $message?->update(['meta' => [...$message->meta ?? [], 'cancelled' => true]]);
    }

    public function update(Conversation $conversation, array $data): Conversation
    {
        return tap($conversation)->update($data);
    }

    public function delete(Conversation $conversation): Conversation
    {
        $conversation->messages()->delete();

        return tap($conversation)->delete();
    }

    /**
     * @return MorphMany<Conversation, User>
     */
    protected function archivedQuery(Project $project): MorphMany
    {
        return $this->user->conversations()
            ->where('project_id', $project->id)
            ->where('is_archived', true);
    }

    public function loadMessages(Conversation $conversation): Conversation
    {
        return $conversation->loadMissing([
            'messages' => function (HasMany $query) {
                $query->oldest();
            },
        ]);
    }

    /**
     * Find the call of the given tool waiting for the user's approval in one of their conversations.
     *
     * @return array{id: string, name: string, arguments: array<string, mixed>, approval_reason: string}|null
     */
    public function findPendingToolCall(Conversation $conversation, string $toolCallId, string $toolName): ?array
    {
        $toolCall = $this->findToolCall($conversation, $toolCallId, $toolName, MessageStatus::Paused);

        return $toolCall !== null && PendingApproval::isPending($toolCall) ? $toolCall : null;
    }

    /**
     * Find a call of the given tool saved in one of the user's conversations, so its arguments can be trusted.
     *
     * @return array{id: string, name: string, arguments: array<string, mixed>}|null
     */
    public function findToolCall(Conversation $conversation, string $toolCallId, string $toolName, ?MessageStatus $status = null): ?array
    {
        if ($conversation->participant_type !== $this->user->getMorphClass() || (string) $conversation->participant_id !== (string) $this->user->getKey()) {
            return null;
        }

        return $conversation->messages()
            ->where('role', 'assistant')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->get()
            ->flatMap(fn (ConversationMessage $message) => $message->tool_calls)
            ->first(fn (array $call) => $call['id'] === $toolCallId && $call['name'] === $toolName);
    }

    /**
     * The tool calls of the conversation still waiting for the user's approval, when its last answer stopped on them.
     *
     * @return Collection<int, PendingApproval>
     */
    public function pendingApprovals(Conversation $conversation): Collection
    {
        $lastAnswer = $conversation->messages()->where('role', 'assistant')->latest()->orderByDesc('id')->first();

        if ($lastAnswer?->status !== MessageStatus::Paused) {
            return collect();
        }

        return collect($lastAnswer->tool_calls)
            ->filter(PendingApproval::isPending(...))
            ->map(fn (array $call): PendingApproval => new PendingApproval($call['id'], $call['name'], $call['arguments'] ?? [], $call['approval_reason'] ?? null))
            ->values();
    }

    public function UIMessages(Conversation $conversation): Collection
    {
        /**
         * @var Collection<int, ConversationMessage> $messages
         */
        $messages = $this->loadMessages($conversation)->messages->keyBy('id');

        return collect(Vercel::toUiMessages($messages))
            ->map(fn (array $uiMessage) => [
                ...$uiMessage,
                'parts' => [
                    ...$this->partsInStepOrder($uiMessage['parts'], $messages->get($uiMessage['id'])),
                    ...$this->modelParts($messages->get($uiMessage['id'])),
                ],
            ]);
    }

    /**
     * Lay the parts of an answer out in the order the model wrote them, step after step, as they were streamed:
     * the text of a step reads before the tools it called. An answer whose steps kept no text reads after its tools.
     *
     * @param  list<array<string, mixed>>  $parts
     * @return list<array<string, mixed>>
     */
    protected function partsInStepOrder(array $parts, ?ConversationMessage $message): array
    {
        $steps = collect($message?->steps ?? []);

        if ($steps->every(fn (array $step) => blank($step['content'] ?? null))) {
            return $this->textPartsLast($parts);
        }

        $remainingParts = collect($parts)->reject(fn (array $part) => $part['type'] === 'text');
        $orderedParts = [];

        foreach ($steps as $step) {
            $stepPartTypes = [
                ...(filled($step['reasoning'] ?? null) ? ['reasoning'] : []),
                ...array_fill(0, count($step['provider_tool_calls'] ?? []), 'custom'),
            ];

            foreach ($stepPartTypes as $type) {
                $key = $remainingParts->search(fn (array $part) => $part['type'] === $type);

                if ($key !== false) {
                    $orderedParts[] = $remainingParts->pull($key);
                }
            }

            if (filled($step['content'] ?? null)) {
                $orderedParts[] = ['type' => 'text', 'text' => $step['content']];
            }

            foreach ($step['tool_calls'] ?? [] as $toolCall) {
                $key = $remainingParts->search(fn (array $part) => ($part['toolCallId'] ?? null) === $toolCall['id']);

                if ($key !== false) {
                    $orderedParts[] = $remainingParts->pull($key);
                }
            }
        }

        return [...$orderedParts, ...$remainingParts->values()];
    }

    /**
     * Move the text parts after the tool calls, so the answer reads after the tools it is based on.
     *
     * @param  list<array<string, mixed>>  $parts
     * @return list<array<string, mixed>>
     */
    protected function textPartsLast(array $parts): array
    {
        [$textParts, $otherParts] = collect($parts)->partition(fn (array $part) => $part['type'] === 'text');

        return [...$otherParts->values(), ...$textParts->values()];
    }

    /**
     * Build the data parts naming the provider and the model that wrote an assistant message,
     * and telling when the user stopped it.
     *
     * @return list<array{type: string, id: string, data: string|bool}>
     */
    protected function modelParts(?ConversationMessage $message): array
    {
        $provider = $message?->meta['provider'] ?? null;
        $model = $message?->meta['model'] ?? null;

        if ($message?->role !== 'assistant' || blank($provider) || blank($model)) {
            return [];
        }

        return [
            ['type' => 'data-provider', 'id' => "data-provider-{$message->id}-{$provider}", 'data' => $provider],
            ['type' => 'data-model', 'id' => "data-model-{$message->id}-{$model}", 'data' => $model],
            ...(($message->meta['cancelled'] ?? false) ? [['type' => 'data-cancelled', 'id' => "data-cancelled-{$message->id}", 'data' => true]] : []),
        ];
    }
}
