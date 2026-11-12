<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;

use function Laravel\Prompts\select;

/**
 * Changes what the agent may do on the database: only read it, or write in it too.
 */
class ModeCommand extends SlashCommand
{
    public function __construct(private readonly Terminal $terminal) {}

    public function name(): string
    {
        return 'mode';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $options = collect(AgentAccessModeEnum::cases())
            ->mapWithKeys(fn (AgentAccessModeEnum $case): array => [$case->value => __("ai.access_modes.{$case->value}")])
            ->all();

        $current = $session->settings->accessMode;
        $chosen = AgentAccessModeEnum::tryFrom($arguments === '' ? select(__('chat_cli.commands.mode'), $options, default: $current->value) : $arguments);

        if ($chosen === null) {
            $this->terminal->failure(__('chat_cli.errors.unknown_value', ['value' => $arguments, 'values' => implode(', ', array_keys($options))]));

            return;
        }

        if ($chosen->allowsWrites() && $session->user->cannot('writeWithAgent', $session->project)) {
            $this->terminal->failure(__('console.privileges.write_denied'));

            return;
        }

        $session->settings = $session->settings->withAccessMode($chosen);

        $this->terminal->success($options[$chosen->value]);
    }
}
