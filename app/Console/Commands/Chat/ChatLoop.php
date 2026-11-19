<?php

namespace App\Console\Commands\Chat;

use App\Console\Commands\Chat\Output\Banner;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\SlashCommands\SlashCommandRegistry;
use App\Console\Commands\Chat\Turn\TranscriptPrinter;
use App\Console\Commands\Chat\Turn\TurnRunner;
use App\Console\ConsoleScript;
use Laravel\Prompts\Prompt;

/**
 * The chat itself: it asks a message, runs it as a command or sends it to the agent, and starts again until the user
 * leaves.
 */
class ChatLoop
{
    public function __construct(
        private readonly MessageInput $input,
        private readonly SlashCommandRegistry $commands,
        private readonly TurnRunner $turns,
        private readonly TranscriptPrinter $transcript,
        private readonly Terminal $terminal,
        private readonly Banner $banner,
    ) {}

    public function run(ChatSession $session): void
    {
        $this->welcome($session);

        Prompt::cancelUsing(fn () => throw new ChatClosed);

        try {
            if ($session->conversation !== null) {
                $this->transcript->print($session->conversation);
                $this->turns->resume($session);
            }

            while (! $session->finished) {
                $this->handle($session, $this->input->ask($session));
            }
        } catch (ChatClosed) {
            // Ctrl+C in a prompt leaves the chat as /quit does.
        } finally {
            Prompt::cancelUsing(null);
        }

        $this->goodbye($session);
    }

    private function handle(ChatSession $session, string $message): void
    {
        if ($message === '') {
            return;
        }

        if (str_starts_with($message, SlashCommandRegistry::PREFIX)) {
            $this->commands->run($session, $message);

            return;
        }

        $session->remember($message);
        $this->turns->send($session, $message);
    }

    /**
     * The screen is emptied first: what was asked to open the chat, like the password, has no place above it.
     */
    private function welcome(ChatSession $session): void
    {
        $theme = $this->terminal->theme;

        $this->terminal->clear();
        $this->banner->print();
        $this->terminal->line(' '.$theme->bold($session->project->name).$theme->muted(' · '.$session->project->driver_config->driver->name.' · '.__("ai.access_modes.{$session->settings->accessMode->value}")));
        $this->terminal->line(' '.$theme->muted(__('chat_cli.welcome')));
        $this->terminal->line();
    }

    /**
     * On the way out the screen is emptied, and only tells the command that opens the chat again: on the conversation
     * the user leaves, which is enough to find its project, or else on the project.
     */
    private function goodbye(ChatSession $session): void
    {
        $theme = $this->terminal->theme;

        $this->terminal->clear();
        $this->banner->print();
        $this->terminal->line(' '.__('chat_cli.goodbye'));

        $this->terminal->line(' '.$theme->muted(__($session->conversation === null ? 'chat_cli.reopen' : 'chat_cli.resume')).' '.$theme->accent($this->resumeCommand($session)));

        $this->terminal->line();
    }

    private function resumeCommand(ChatSession $session): string
    {
        return implode(' ', [
            ConsoleScript::name(),
            'chat',
            '--name='.escapeshellarg($session->user->name),
            $session->conversation === null ? "--project={$session->project->id}" : "--conversation={$session->conversation->id}",
        ]);
    }
}
