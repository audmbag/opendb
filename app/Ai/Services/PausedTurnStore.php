<?php

namespace App\Ai\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;

/**
 * The turn of an agent paused for the approval of a tool call, kept on the server for an exchange not saved as a
 * conversation, whose history the client sends back.
 *
 * The client only knows the text and the tool calls of the turn. Each provider also needs what it returned with them
 * to go on: the thought signatures of Gemini, the signed thinking blocks of Anthropic, the reasoning items of OpenAI
 * and OpenRouter. The turn is rebuilt step by step from what the stream held, as a saved conversation would be.
 *
 * @phpstan-type StoredStep array{content: string, tool_calls: list<array<string, mixed>>, tool_results: list<array<string, mixed>>, replay_blocks: array<int, mixed>}
 */
class PausedTurnStore
{
    /**
     * How long a paused turn waits for the answer of the user.
     */
    public const int TTL_SECONDS = 86400;

    /**
     * Keep the steps of the turn if it paused for an approval.
     *
     * @param  string  $scope  who the exchange belongs to, so that a client never resumes the turn of another
     */
    public function remember(string $scope, StreamedAgentResponse $response): void
    {
        $pause = $response->events->last(fn (object $event): bool => $event instanceof ToolApprovalRequest);

        if (! $pause instanceof ToolApprovalRequest || $pause->steps->isEmpty()) {
            return;
        }

        $start = $response->events->last(fn (object $event): bool => $event instanceof StreamStart);
        $turn = [
            'provider' => $start instanceof StreamStart ? $start->provider : $response->meta->provider,
            'steps' => $pause->steps->values()->map(fn (Step $step): array => [
                'content' => $step->text,
                'tool_calls' => array_values(array_map(fn (ToolCall $toolCall): array => $toolCall->toArray(), $step->toolCalls)),
                'tool_results' => array_values(array_map(fn (ToolResult $toolResult): array => $toolResult->toArray(), $step->toolResults)),
                'replay_blocks' => $step->replayBlocks,
            ])->all(),
        ];

        foreach ($pause->pendingApprovals as $pendingApproval) {
            Cache::put($this->key($scope, $pendingApproval->id), $turn, self::TTL_SECONDS);
        }
    }

    /**
     * The history sent back by the client, its last assistant turn rebuilt from the steps kept when it paused.
     * Without them, the history is left as the client sent it.
     *
     * @param  list<Message>  $history
     * @return list<Message>
     */
    public function restore(string $scope, array $history): array
    {
        $position = array_find_key(array_reverse($history, true), fn (Message $message): bool => $message instanceof AssistantMessage);

        if ($position === null) {
            return $history;
        }

        /** @var AssistantMessage $paused */
        $paused = $history[$position];
        $turn = $paused->toolCalls
            ->map(fn (ToolCall $toolCall): mixed => Cache::get($this->key($scope, $toolCall->id)))
            ->first(fn (mixed $turn): bool => is_array($turn));

        if ($turn === null) {
            return $history;
        }

        $rebuilt = (new Collection($turn['steps']))->flatMap(function (array $step) use ($turn): array {
            $messages = [new AssistantMessage(
                $step['content'],
                (new Collection($step['tool_calls']))->map(ToolCall::fromArray(...)),
                $step['replay_blocks'],
                $turn['provider'],
            )];

            if ($step['tool_results'] !== []) {
                $messages[] = new ToolResultMessage((new Collection($step['tool_results']))->map(ToolResult::fromArray(...)));
            }

            return $messages;
        })->all();

        // The results the client read for the paused turn are those of its steps, rebuilt with them.
        $after = array_slice($history, $position + 1);
        $after = array_values(array_filter($after, fn (Message $message): bool => ! $message instanceof ToolResultMessage));

        return [...array_slice($history, 0, $position), ...$rebuilt, ...$after];
    }

    protected function key(string $scope, string $toolCallId): string
    {
        return "ai:paused-turn:{$scope}:{$toolCallId}";
    }
}
