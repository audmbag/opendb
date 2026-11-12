<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;

/**
 * A command of the chat, typed with a slash instead of a message: what the buttons and the menus are to the web chat.
 */
abstract class SlashCommand
{
    /**
     * The name typed after the slash.
     */
    abstract public function name(): string;

    /**
     * @param  string  $arguments  what was typed after the name, possibly nothing
     */
    abstract public function run(ChatSession $session, string $arguments): void;

    /**
     * @return list<string> the other names running the command
     */
    public function aliases(): array
    {
        return [];
    }

    public function description(): string
    {
        return __("chat_cli.commands.{$this->name()}");
    }
}
