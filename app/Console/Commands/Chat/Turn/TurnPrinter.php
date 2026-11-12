<?php

namespace App\Console\Commands\Chat\Turn;

use App\Ai\Support\StreamErrorMessage;
use App\Console\Commands\Chat\Output\MarkdownPrinter;
use App\Console\Commands\Chat\Output\StreamingText;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Output\Theme;
use App\Console\Commands\Chat\Tools\ToolRendererRegistry;
use App\Support\TranslatableMessage;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Throwable;

/**
 * Writes an answer of the agent as it arrives: each tool it calls, with what it answered, and the text block by block.
 */
class TurnPrinter
{
    private StreamingText $text;

    private ?StreamStart $start = null;

    /**
     * @var array<string, float> when each tool call was shown, by its id
     */
    private array $toolCallTimes = [];

    /**
     * The id of the tool call whose frame is still open, waiting for its result.
     */
    private ?string $framedToolCall = null;

    public function __construct(
        private readonly Terminal $terminal,
        private readonly MarkdownPrinter $markdownPrinter,
        private readonly ToolRendererRegistry $toolRenderers,
        private readonly WaitIndicator $indicator,
    ) {}

    public function print(StreamableAgentResponse $response): TurnResult
    {
        $this->text = new StreamingText;
        $this->start = null;
        $this->toolCallTimes = [];
        $this->framedToolCall = null;

        $startedAt = microtime(true);
        $pendingApprovals = new Collection;
        $error = null;

        $this->indicator->start(__('ai.activities.thinking'));

        try {
            foreach ($response as $event) {
                if ($event instanceof ToolApprovalRequest) {
                    $pendingApprovals = $pendingApprovals->merge($event->pendingApprovals);
                }

                $this->printEvent($event);
            }
        } catch (Throwable $exception) {
            $error = StreamErrorMessage::for($exception) ?? __('chat_cli.errors.unexpected');

            if (StreamErrorMessage::for($exception) === null) {
                report($exception);
            }
        } finally {
            $this->indicator->stop();
        }

        $this->terminal->closeFrame();
        $this->printText($this->text->flush());

        if ($error !== null) {
            $this->terminal->failure($error);
        }

        if ($pendingApprovals->isEmpty()) {
            $this->printFooter($response, microtime(true) - $startedAt);
        }

        return new TurnResult($pendingApprovals);
    }

    private function printEvent(StreamEvent $event): void
    {
        match (true) {
            $event instanceof StreamStart => $this->start = $event,
            $event instanceof TextDelta => $this->printTextDelta($event),
            $event instanceof ToolCall => $this->printToolCall($event),
            $event instanceof ToolResult => $this->printToolResult($event),
            $event instanceof StreamEnd => $this->indicator->stop(),
            default => null,
        };
    }

    private function printTextDelta(TextDelta $event): void
    {
        $blocks = $this->text->push($event->delta);

        array_walk($blocks, $this->printText(...));

        $this->indicator->start(__('ai.activities.writing'));
    }

    private function printText(?string $markdown): void
    {
        if (blank($markdown)) {
            return;
        }

        $this->indicator->stop();
        $this->terminal->closeFrame();
        $this->terminal->lines($this->markdownPrinter->lines($markdown, $this->terminal->width()));
        $this->terminal->line();
    }

    /**
     * Each tool call is written in a frame, closed by its result: the frame tells it apart from the answer.
     */
    private function printToolCall(ToolCall $event): void
    {
        $this->printText($this->text->flush());
        $this->indicator->stop();

        $tool = $event->toolCall->name;

        $this->toolCallTimes[$event->toolCall->id] = microtime(true);
        $this->framedToolCall = $event->toolCall->id;

        $this->terminal->openFrame($this->toolTitle($tool));
        $this->terminal->frameLines($this->toolRenderers->for($tool)->call($event->toolCall->arguments));

        $this->indicator->start($this->toolRenderers->activity($tool).'…');
    }

    /**
     * A result whose call is not the last one written, like the one of a call the user just approved, opens its own frame.
     */
    private function printToolResult(ToolResult $event): void
    {
        if ($event->preliminary) {
            return;
        }

        $this->indicator->stop();

        $theme = $this->terminal->theme;
        $result = $event->toolResult;
        $renderer = $this->toolRenderers->for($result->name);
        $duration = $this->seconds(microtime(true) - ($this->toolCallTimes[$result->id] ?? microtime(true)));

        if ($this->framedToolCall !== $result->id) {
            $this->terminal->openFrame($this->toolTitle($result->name));
        }

        $failure = match (true) {
            $event->denied || $result->denied => __('chat_cli.tools.denied'),
            $this->isUnavailable($result->result) => __('chat_cli.tools.unavailable'),
            ! $event->successful || $result->failed => TranslatableMessage::text($event->error ?? $result->result),
            default => $renderer->error($result->result),
        };

        if ($failure !== null) {
            $this->terminal->frameLines([$theme->danger(Theme::FAILURE.' '.$failure)]);
        } else {
            $summary = $renderer->summary($result->result);

            $this->terminal->frameLines([
                $theme->success(Theme::SUCCESS).$theme->muted(' '.$duration.($summary === null ? '' : ' · '.$summary)),
                ...$renderer->details($result->result, $result->arguments),
            ]);
        }

        $this->terminal->closeFrame();
        $this->framedToolCall = null;

        $this->indicator->start(__('ai.activities.analyzing_result'));
    }

    /**
     * Whether the agent called a tool it does not have, like a write in read-only mode. laravel/ai answers such a call
     * with this sentence, written for the model: the user is told what to do instead.
     */
    private function isUnavailable(mixed $result): bool
    {
        return is_string($result) && preg_match("/^Tool '[^']+' does not exist\\./", $result) === 1;
    }

    private function toolTitle(string $tool): string
    {
        $theme = $this->terminal->theme;

        return $theme->accent(Theme::TOOL).' '.$theme->bold($tool).$theme->muted(' · '.$this->toolRenderers->activity($tool));
    }

    /**
     * The model that answered, what the answer cost and how long it took.
     */
    private function printFooter(StreamableAgentResponse $response, float $seconds): void
    {
        $tokens = ($response->usage?->inputTokens ?? 0) + ($response->usage?->outputTokens ?? 0);

        $parts = [
            $this->start === null ? null : "{$this->start->provider}/{$this->start->model}",
            $tokens > 0 ? trans_choice('chat_cli.turn.tokens', $tokens, ['count' => TranslatableMessage::formatNumber($tokens)]) : null,
            $this->seconds($seconds),
        ];

        $this->terminal->line($this->terminal->theme->muted(implode(' · ', array_filter($parts))));
        $this->terminal->line();
    }

    private function seconds(float $seconds): string
    {
        return TranslatableMessage::formatNumber($seconds, 'decimal').' s';
    }
}
