<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\Attachments;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;

/**
 * Joins a file to the next message. Without a path it lists the joined files; with "clear" it removes them all.
 */
class AttachCommand extends SlashCommand
{
    private const string CLEAR = 'clear';

    public function __construct(
        private readonly Attachments $attachments,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'attach';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        if ($arguments === self::CLEAR) {
            $session->attachments = [];
        }

        if ($arguments !== '' && $arguments !== self::CLEAR && ($error = $this->attachments->add($session, $arguments)) !== null) {
            $this->terminal->failure($error);

            return;
        }

        if ($session->attachments === []) {
            $this->terminal->notice(__('chat_cli.attachments.none'));

            return;
        }

        $this->terminal->line(trans_choice('chat_cli.attachments.joined', count($session->attachments)));
        $this->terminal->lines($session->attachments, '  ');
        $this->terminal->line();
    }
}
