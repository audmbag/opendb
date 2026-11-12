<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

use App\Ai\Agents\Erd\SchemaDesignerAgent;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Ai\Services\PausedTurnStore;
use App\Database\Contracts\DriverContract;
use App\Services\TableDesigner\TableDesignFailedException;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Vercel\Chat;

/**
 * The assistant of the diagram: the exchange with its agent, and the draft of the changes it asks for, drawn before
 * they run.
 *
 * @phpstan-import-type DraftTable from SchemaDraft
 */
class SchemaAssistant
{
    public function __construct(
        protected SchemaChangePlanner $planner,
        protected SchemaChangeRunner $runner,
        protected SchemaDraft $draft,
        protected PausedTurnStore $pausedTurns,
    ) {}

    /**
     * The exchange is not saved: its history comes from the client. A turn paused for the approval of changes is kept
     * on the server with what the provider needs to go on, and rebuilt from it once the user answers.
     *
     * @param  string  $scope  who the exchange belongs to
     */
    public function chat(DriverContract $driver, Chat $chat, string $scope, SchemaChangeApplyModeEnum $mode, int $steps, Lab|string $provider, string $model): StreamableAgentResponse
    {
        $history = $chat->decisions() === null
            ? $chat->history()
            : $this->pausedTurns->restore($scope, $chat->history());

        return new SchemaDesignerAgent($driver, $mode, $steps)
            ->withMessages($history)
            ->stream($chat, provider: $provider, model: $model)
            ->then(fn (StreamedAgentResponse $response) => $this->pausedTurns->remember($scope, $response));
    }

    /**
     * The changes read against the database, the statements they would run, and the tables they would draw.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return array{valid: bool, destructive: bool, changes: list<array<string, mixed>>, errors: list<string>, statements: list<string>, failure: ?array{message: string, statement: string}, draft: array{tables: list<DraftTable>, dropped: list<string>}}
     */
    public function preview(DriverContract $driver, array $changes): array
    {
        $plan = $this->planner->plan($driver, $changes);
        $statements = [];
        $failure = null;

        if ($plan->isValid()) {
            try {
                $statements = $this->runner->preview($driver, $plan);
            } catch (TableDesignFailedException $exception) {
                $failure = ['message' => $exception->getMessage(), 'statement' => $exception->statement];
            }
        }

        return [
            'valid' => $plan->isValid(),
            'destructive' => $plan->isDestructive(),
            'changes' => $plan->toArray(),
            'errors' => $plan->errors(),
            'statements' => $statements,
            'failure' => $failure,
            'draft' => $this->draft->from($plan),
        ];
    }
}
