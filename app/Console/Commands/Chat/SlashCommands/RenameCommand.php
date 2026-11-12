<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Repository\ConversationRepository;

use function Laravel\Prompts\text;

/**
 * Gives another title to the conversation.
 */
class RenameCommand extends SlashCommand
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'rename';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        if ($session->conversation === null) {
            $this->terminal->failure(__('chat_cli.errors.no_conversation'));

            return;
        }

        $title = $arguments !== '' ? $arguments : text(
            __('conversations.title_placeholder'),
            default: (string) $session->conversation->title,
            required: true,
            validate: ['title' => 'min:3|max:255'],
        );

        if (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
            $this->terminal->failure(__('validation.between.string', ['attribute' => __('conversations.title_placeholder'), 'min' => 3, 'max' => 255]));

            return;
        }

        $this->conversationRepository->update($session->conversation, ['title' => $title]);

        $this->terminal->success(__('conversations.title_updated'));
    }
}
