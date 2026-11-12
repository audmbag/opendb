<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Turn\TurnRunner;

/**
 * Asks the agent to answer the last message again, as the web chat does: the previous answer is replaced.
 */
class RetryCommand extends SlashCommand
{
    public function __construct(
        private readonly TurnRunner $turns,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'retry';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        if (! $this->turns->retry($session)) {
            $this->terminal->failure(__('chat_cli.errors.nothing_to_retry'));
        }
    }
}
