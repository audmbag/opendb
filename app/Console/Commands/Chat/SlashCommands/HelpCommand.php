<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;

/**
 * Lists the commands of the chat.
 */
class HelpCommand extends SlashCommand
{
    public function __construct(
        private readonly Terminal $terminal,
        private readonly SlashCommandRegistry $commands,
    ) {}

    public function name(): string
    {
        return 'help';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $theme = $this->terminal->theme;

        foreach ($this->commands->all() as $command) {
            $this->terminal->line('  '.$theme->accent(str_pad(SlashCommandRegistry::PREFIX.$command->name(), 16)).$command->description());
        }

        $this->terminal->line();
        $this->terminal->notice(__('chat_cli.help.keys'));
    }
}
