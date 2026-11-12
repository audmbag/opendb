<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\ModelChooser;
use App\Console\Commands\Chat\Output\Terminal;

/**
 * Changes the model answering the next messages.
 */
class ModelCommand extends SlashCommand
{
    public function __construct(
        private readonly ModelChooser $modelChooser,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'model';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $current = $this->modelChooser->key($session->settings->provider, $session->settings->model);
        $chosen = $this->modelChooser->choose($arguments === '' ? null : $arguments, $current);

        if ($chosen === null) {
            $this->terminal->failure(__('chat_cli.errors.unknown_model', ['model' => $arguments]));

            return;
        }

        $session->settings = $session->settings->withModel(...$chosen);

        $this->terminal->success($this->modelChooser->key(...$chosen));
    }
}
