<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;

/**
 * Empties the screen. The conversation is not touched.
 */
class ClearCommand extends SlashCommand
{
    public function __construct(private readonly Terminal $terminal) {}

    public function name(): string
    {
        return 'clear';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $this->terminal->clear();
    }
}
