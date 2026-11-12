<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Repository\ConversationRepository;

use function Laravel\Prompts\confirm;

/**
 * Deletes the conversation and its messages, once the user has confirmed.
 */
class DeleteCommand extends SlashCommand
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'delete';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        if ($session->conversation === null) {
            $this->terminal->failure(__('chat_cli.errors.no_conversation'));

            return;
        }

        if (! confirm(__('conversations.delete_title'), default: false, hint: __('conversations.delete_description'))) {
            return;
        }

        $this->conversationRepository->delete($session->conversation);
        $session->conversation = null;

        $this->terminal->success(__('conversations.deleted'));
    }
}
