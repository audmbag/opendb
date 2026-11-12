<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Ai\Enums\AgentEffortEnum;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;

use function Laravel\Prompts\select;

/**
 * Changes how hard the model thinks before answering.
 */
class EffortCommand extends SlashCommand
{
    public function __construct(private readonly Terminal $terminal) {}

    public function name(): string
    {
        return 'effort';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $options = collect(AgentEffortEnum::cases())
            ->mapWithKeys(fn (AgentEffortEnum $case): array => [$case->value => __("ai.efforts.{$case->value}")])
            ->all();

        $current = $session->settings->effort;
        $chosen = AgentEffortEnum::tryFrom($arguments === '' ? select(__('chat_cli.commands.effort'), $options, default: $current->value) : $arguments);

        if ($chosen === null) {
            $this->terminal->failure(__('chat_cli.errors.unknown_value', ['value' => $arguments, 'values' => implode(', ', array_keys($options))]));

            return;
        }

        $session->settings = $session->settings->withEffort($chosen);

        $this->terminal->success($options[$chosen->value]);
    }
}
