<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;

/**
 * Leaves the conversation for a new one, started by the next message.
 */
class NewCommand extends SlashCommand
{
    public function __construct(private readonly Terminal $terminal) {}

    public function name(): string
    {
        return 'new';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $session->conversation = null;

        $this->terminal->notice(__('conversations.new_conversation'));
    }
}
