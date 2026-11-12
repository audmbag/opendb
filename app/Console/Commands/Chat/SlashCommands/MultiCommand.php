<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Turn\TurnRunner;

use function Laravel\Prompts\textarea;

/**
 * Writes a message of several lines, which Enter would send too early.
 */
class MultiCommand extends SlashCommand
{
    public function __construct(private readonly TurnRunner $turns) {}

    public function name(): string
    {
        return 'multi';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $message = trim(textarea(__('chat_cli.input.label'), placeholder: __('chat_cli.input.placeholder')));

        if ($message !== '') {
            $this->turns->send($session, $message);
        }
    }
}
