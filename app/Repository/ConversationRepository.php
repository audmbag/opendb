<?php

namespace App\Repository;

use App\Ai\Services\ProviderManagerService;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function create(Project $project, string $message): ?Conversation
    {
        return $this->user->conversations()->create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'title' => ProviderManagerService::generateConversationTitle($message),
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

    public function archive(Conversation $conversation): Conversation
    {
        return tap($conversation)->update(['is_archived' => true]);
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
                    ...$this->textPartsLast($uiMessage['parts']),
                    ...$this->modelParts($messages->get($uiMessage['id'])),
                ],
            ]);
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
     * Build the data parts naming the provider and the model that wrote an assistant message.
     *
     * @return list<array{type: string, id: string, data: string}>
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
        ];
    }
}
