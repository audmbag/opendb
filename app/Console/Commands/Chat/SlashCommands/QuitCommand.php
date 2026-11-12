<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;

/**
 * Leaves the chat. The conversation stays, in the console as in the web chat.
 */
class QuitCommand extends SlashCommand
{
    public function name(): string
    {
        return 'quit';
    }

    /**
     * {@inheritdoc}
     */
    public function aliases(): array
    {
        return ['exit'];
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $session->finished = true;
    }
}
