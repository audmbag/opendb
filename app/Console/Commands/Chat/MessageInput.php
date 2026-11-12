<?php

namespace App\Console\Commands\Chat;

use App\Console\Commands\Chat\SlashCommands\SlashCommandRegistry;
use Illuminate\Support\Str;
use Laravel\Prompts\SuggestPrompt;

use function Laravel\Prompts\suggest;

/**
 * Asks the next message. Enter sends it, the commands are suggested as soon as it starts with a slash, and the arrows
 * bring back the messages already sent. Before the first message, the arrows go through questions to start with.
 */
class MessageInput
{
    private const int TITLE_LENGTH = 40;

    public function __construct(
        private readonly SlashCommandRegistry $commands,
        private readonly ModelChooser $modelChooser,
    ) {}

    public function ask(ChatSession $session): string
    {
        $history = $session->sentMessages();

        $arguments = [
            'label' => __('chat_cli.input.label'),
            'options' => fn (string $value): array => match (true) {
                str_starts_with($value, SlashCommandRegistry::PREFIX) => $this->commands->matching($value),
                $value === '' && $history === [] => array_values((array) __('ai.suggestions')),
                default => [],
            },
            'placeholder' => __('chat_cli.input.placeholder'),
            'hint' => implode(' · ', array_filter([
                Str::limit($session->conversation?->title ?? '', self::TITLE_LENGTH, '…'),
                $session->project->name,
                $this->modelChooser->key($session->settings->provider, $session->settings->model),
                __("ai.access_modes.{$session->settings->accessMode->value}"),
                $session->attachments === [] ? null : trans_choice('chat_cli.attachments.joined', count($session->attachments)),
            ])),
        ];

        // Where a prompt cannot be drawn, like in a test, Laravel asks the question its own way.
        return trim(SuggestPrompt::shouldFallback()
            ? suggest(...$arguments)
            : new MessagePrompt(...$arguments)->withHistory($history)->prompt());
    }
}
