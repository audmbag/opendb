<?php

namespace App\Console\Commands\Chat\Turn;

use App\Console\Commands\Chat\Output\MarkdownPrinter;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Output\Theme;
use App\Console\Commands\Chat\Tools\ToolRendererRegistry;
use App\Repository\ConversationRepository;
use App\Support\TranslatableMessage;
use Laravel\Ai\Models\Conversation;

/**
 * Writes the end of a conversation the user comes back to, with the tool calls of the agent, so they read where they
 * left it.
 */
class TranscriptPrinter
{
    private const int LAST_MESSAGES = 6;

    private const string TOOL_PART_PREFIX = 'tool-';

    public function __construct(
        private readonly Terminal $terminal,
        private readonly MarkdownPrinter $markdownPrinter,
        private readonly ToolRendererRegistry $toolRenderers,
        private readonly ConversationRepository $conversationRepository,
    ) {}

    public function print(Conversation $conversation): void
    {
        $theme = $this->terminal->theme;
        $messages = $this->conversationRepository->UIMessages($conversation);

        $this->terminal->line($theme->bold($conversation->title ?? __('conversations.untitled')));
        $this->terminal->line();

        if ($messages->count() > self::LAST_MESSAGES) {
            $this->terminal->line($theme->muted(trans_choice('chat_cli.transcript.earlier', $messages->count() - self::LAST_MESSAGES)));
            $this->terminal->line();
        }

        foreach ($messages->take(-self::LAST_MESSAGES) as $message) {
            foreach ($message['parts'] as $part) {
                $this->printPart($message['role'], $part);
            }
        }
    }

    /**
     * The parts of a message are the ones the web chat shows, in the same order: its texts and its tool calls.
     *
     * @param  array<string, mixed>  $part
     */
    private function printPart(string $role, array $part): void
    {
        $type = (string) ($part['type'] ?? '');

        if (str_starts_with($type, self::TOOL_PART_PREFIX)) {
            $this->printToolCall(substr($type, strlen(self::TOOL_PART_PREFIX)), $part);

            return;
        }

        if ($type !== 'text' || blank($part['text'] ?? null)) {
            return;
        }

        $this->terminal->lines($role === 'user'
            ? [$this->terminal->theme->accent(Theme::PROMPT).' '.$part['text']]
            : $this->markdownPrinter->lines($part['text'], $this->terminal->width()));
        $this->terminal->line();
    }

    /**
     * A call still waiting for the approval of the user is left out: the chat asks about it right after.
     *
     * @param  array<string, mixed>  $part
     */
    private function printToolCall(string $tool, array $part): void
    {
        $theme = $this->terminal->theme;
        $renderer = $this->toolRenderers->for($tool);
        $state = $part['state'] ?? null;
        $output = $part['output'] ?? null;

        if ($state === 'approval-requested') {
            return;
        }

        $failure = match (true) {
            $state === 'output-denied' => __('chat_cli.tools.denied'),
            $state === 'output-error' => TranslatableMessage::text($part['errorText'] ?? $output),
            $state === 'output-available' => $renderer->error($output),
            default => null,
        };

        $status = match (true) {
            $failure !== null => [$theme->danger(Theme::FAILURE.' '.$failure)],
            $state === 'output-available' => [$theme->success(Theme::SUCCESS).$theme->muted(' '.($renderer->summary($output) ?? __('chat_cli.tools.done')))],
            default => [],
        };

        $this->terminal->openFrame($theme->accent(Theme::TOOL).' '.$theme->bold($tool).$theme->muted(' · '.$this->toolRenderers->activity($tool)));
        $input = (array) ($part['input'] ?? []);

        // What the call displayed, like the preview of an artifact, is read again as it was under the call.
        $details = $failure === null && $state === 'output-available' ? $renderer->details($output, $input) : [];

        $this->terminal->frameLines([...$renderer->call($input), ...$status, ...$details]);
        $this->terminal->closeFrame();
    }
}
