<?php

namespace App\Repository;

use App\Ai\Services\ProviderManagerService;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Storage;

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

    protected function toUiMessage(ConversationMessage $message): ?array
    {
        if (! in_array($message->role, ['user', 'assistant'])) {
            return null;
        }

        $parts = [];

        foreach (($message->attachments ?? []) as $attachment) {
            $parts[] = [
                'type' => 'file',
                'mediaType' => Storage::disk($attachment['disk'])->mimeType($attachment['path']),
                'filename' => $attachment['name'],
                'url' => Storage::disk($attachment['disk'])->url($attachment['path']),
            ];
        }

        if ($message->role === 'assistant') {
            $calls = $message->tool_calls ?: [];
            $results = collect($message->tool_results ?: [])->keyBy('id');
            $pendingApprovals = $message->approval_state['pending'] ?? [];

            foreach ($calls as $call) {
                $pendingReason = $pendingApprovals[$call['id']] ?? null;

                if ($pendingReason !== null) {
                    $parts[] = [
                        'type' => 'tool-'.$call['name'],
                        'toolCallId' => $call['id'],
                        'state' => 'approval-requested',
                        'input' => $call['arguments'] ?? [],
                        'approval' => [
                            'id' => $call['id'],
                            'reason' => $pendingReason,
                        ],
                    ];

                    continue;
                }

                $result = $results->get($call['id']);

                $parts[] = [
                    'type' => 'tool-'.$call['name'],
                    'toolCallId' => $call['id'],
                    'state' => $result ? 'output-available' : 'input-available',
                    'input' => $call['arguments'] ?? [],
                    'output' => $result['output'] ?? $result['result'] ?? null,
                    'errorText' => $result['error'] ?? null,
                ];
            }
        }

        if (filled($message->content)) {
            $parts[] = ['type' => 'text', 'text' => $message->content];
        }

        if (! empty($message->meta)) {
            $parts[] = [
                'type' => 'data-provider',
                'id' => "data-provider-{$message->id}-{$message->meta['provider']}",
                'data' => $message->meta['provider'],
            ];
            $parts[] = [
                'type' => 'data-model',
                'id' => "data-model-{$message->id}-{$message->meta['model']}",
                'data' => $message->meta['model'],
            ];
        }

        return [
            'id' => $message->id,
            'role' => $message->role,
            'parts' => $parts,
        ];
    }

    public function UIMessages(Conversation $conversation): Collection
    {
        /**
         * @var Collection $messages
         */
        $messages = $this->loadMessages($conversation)->messages;

        return $messages->map(fn (ConversationMessage $message) => $this->toUiMessage($message))
            ->filter()
            ->values();
    }
}
