<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Repository\ConversationRepository;

/**
 * Archives the conversation: it leaves the list, and can be restored from the settings of the project.
 */
class ArchiveCommand extends SlashCommand
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'archive';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        if ($session->conversation === null) {
            $this->terminal->failure(__('chat_cli.errors.no_conversation'));

            return;
        }

        $this->conversationRepository->archive($session->conversation);
        $session->conversation = null;

        $this->terminal->success(__('conversations.archived'));
    }
}
