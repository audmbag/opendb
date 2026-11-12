<?php

namespace App\Console\Commands\Chat;

use App\Ai\Data\AgentSettings;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Models\Conversation;

/**
 * What the chat knows while it runs: who is chatting, on which project, in which conversation and with which model.
 */
final class ChatSession
{
    /**
     * Null until the first message is sent: a conversation nobody wrote in is never saved.
     */
    public ?Conversation $conversation = null;

    public bool $finished = false;

    /**
     * The last message sent when its answer failed or was stopped: such a message is not saved in the conversation.
     */
    public ?string $unansweredMessage = null;

    /**
     * @var list<string> the paths of the files joined to the next message
     */
    public array $attachments = [];

    /**
     * @var list<string> the messages sent since the chat was opened
     */
    private array $typedMessages = [];

    /**
     * @param  bool  $interactive  false when the question was given with the command: nobody is there to answer a prompt
     */
    public function __construct(
        public readonly User $user,
        public readonly Project $project,
        public AgentSettings $settings,
        public readonly bool $interactive = true,
    ) {}

    /**
     * The address of the conversation in the web chat, null while no conversation is saved.
     */
    public function webUrl(): ?string
    {
        return $this->conversation === null
            ? null
            : route('conversation.chat', ['project' => $this->project, 'conversation' => $this->conversation]);
    }

    public function remember(string $message): void
    {
        $this->typedMessages[] = $message;
    }

    /**
     * The messages the user can bring back in the field: those of the conversation, then those sent since the chat was
     * opened, the oldest first.
     *
     * @return list<string>
     */
    public function sentMessages(): array
    {
        $saved = $this->conversation?->messages()->where('role', 'user')->oldest()->orderBy('id')->pluck('content')->all() ?? [];

        return array_values(array_unique([...array_diff($saved, $this->typedMessages), ...$this->typedMessages]));
    }
}
