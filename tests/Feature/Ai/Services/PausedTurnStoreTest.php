<?php

use App\Ai\Services\PausedTurnStore;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;

/**
 * A turn of two steps paused on its second tool call, with what the provider returned with each step.
 */
function pausedTurnResponse(string $provider = 'anthropic'): StreamedAgentResponse
{
    $step = fn (string $text, array $toolCalls, array $toolResults, array $replayBlocks): Step => new Step(
        $text, $toolCalls, $toolResults, FinishReason::ToolCalls, new TextUsage, new Meta($provider, 'model'), '', $replayBlocks,
    );

    return new StreamedAgentResponse('invocation', new Collection([
        new StreamStart('start', $provider, 'model', 0),
        new ToolApprovalRequest('pause', new Collection([new PendingApproval('call_2', 'ProposeSchemaChanges', ['changes' => []])]), 0, new Collection([
            $step('', [new ToolCall('call_1', 'FocusTable', ['table' => 'customers'], thoughtSignature: 'signature-1')], [new ToolResult('call_1', 'FocusTable', ['table' => 'customers'], '{"columns":[]}')], [['type' => 'thinking', 'signature' => 'block-1']]),
            $step('Je propose la table.', [new ToolCall('call_2', 'ProposeSchemaChanges', ['changes' => []], thoughtSignature: 'signature-2')], [], [['type' => 'thinking', 'signature' => 'block-2']]),
        ])),
    ]), new Meta($provider, 'model'));
}

/**
 * The history the client sends back once the user answered: its paused turn merged into one message.
 *
 * @return list<Message>
 */
function historyFromClient(): array
{
    return [
        new UserMessage('Ajoute une table invoices'),
        new AssistantMessage('Je propose la table.', new Collection([
            new ToolCall('call_1', 'FocusTable', ['table' => 'customers']),
            new ToolCall('call_2', 'ProposeSchemaChanges', ['changes' => []]),
        ])),
        new ToolResultMessage(new Collection([new ToolResult('call_1', 'FocusTable', ['table' => 'customers'], '{"columns":[]}')])),
    ];
}

it('rebuilds the paused turn step by step with what the provider returned with each one', function () {
    $store = new PausedTurnStore;
    $store->remember('erd:1:project', pausedTurnResponse());

    $history = $store->restore('erd:1:project', historyFromClient());

    expect(array_map(fn ($message) => $message::class, $history))->toBe([UserMessage::class, AssistantMessage::class, ToolResultMessage::class, AssistantMessage::class])
        ->and($history[1]->replayBlocks)->toBe([['type' => 'thinking', 'signature' => 'block-1']])
        ->and($history[1]->replayBlocksProvider)->toBe('anthropic')
        ->and($history[1]->toolCalls->first()->thoughtSignature)->toBe('signature-1')
        ->and($history[2]->toolResults->first()->id)->toBe('call_1')
        ->and($history[3]->content)->toBe('Je propose la table.')
        ->and($history[3]->toolCalls->first()->thoughtSignature)->toBe('signature-2')
        ->and($history[3]->replayBlocks)->toBe([['type' => 'thinking', 'signature' => 'block-2']]);
});

it('leaves the history of the client as it is without a turn kept for it', function (string $scope) {
    $store = new PausedTurnStore;
    $store->remember('erd:1:project', pausedTurnResponse());

    expect($store->restore($scope, historyFromClient()))->toEqual(historyFromClient());
})->with([
    'another user' => ['erd:2:project'],
    'another project' => ['erd:1:other'],
]);

it('keeps nothing from a turn that did not pause', function () {
    $store = new PausedTurnStore;
    $store->remember('erd:1:project', new StreamedAgentResponse('invocation', new Collection([new StreamStart('start', 'openai', 'model', 0)]), new Meta('openai', 'model')));

    expect($store->restore('erd:1:project', historyFromClient()))->toEqual(historyFromClient());
});
