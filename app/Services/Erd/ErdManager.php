<?php

declare(strict_types=1);

namespace App\Services\Erd;

use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Database\Contracts\DriverContract;
use App\Services\Erd\Assistant\SchemaAssistant;
use App\Services\SqlEditor\SqlFileException;
use App\Services\Workspace\ProjectWorkspace;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Vercel\Chat;

/**
 * Passerelle entre l'application et l'onglet Schéma ERD : la disposition du diagramme, son diagnostic et l'assistant
 * qui en change la structure.
 */
final readonly class ErdManager
{
    public function __construct(
        private ErdLayoutStore $layouts,
        private SchemaDiagnostics $diagnostics,
        private SchemaAssistant $assistant,
    ) {}

    /**
     * The tables the user placed by hand in their diagram of the project, but those they may no longer see.
     *
     * @param  list<string>  $hiddenTables
     * @return list<array{table: string, x: float, y: float}>
     */
    public function layout(ProjectWorkspace $workspace, array $hiddenTables = []): array
    {
        return $this->layouts->read($workspace)
            |> (fn (array $positions): array => array_filter($positions, fn (array $position): bool => ! in_array($position['table'], $hiddenTables, true)))
            |> array_values(...);
    }

    /**
     * @param  list<array{table: string, x: float|int|string, y: float|int|string}>  $positions
     *
     * @throws SqlFileException when the directory of the project cannot be written
     */
    public function saveLayout(ProjectWorkspace $workspace, array $positions): void
    {
        $this->layouts->write($workspace, $positions);
    }

    /**
     * @return array{summary: array{total: int, error: int, warning: int, info: int}, findings: list<array<string, mixed>>}
     */
    public function diagnostics(DriverContract $driver): array
    {
        return $this->diagnostics->diagnose($driver);
    }

    /**
     * A message to the assistant of the diagram, answered in a stream without being saved as a conversation.
     *
     * @param  string  $scope  who the exchange belongs to
     */
    public function chat(DriverContract $driver, Chat $chat, string $scope, SchemaChangeApplyModeEnum $mode, int $steps, Lab|string $provider, string $model): StreamableAgentResponse
    {
        return $this->assistant->chat($driver, $chat, $scope, $mode, $steps, $provider, $model);
    }

    /**
     * The draft of the changes asked by the assistant, drawn in the diagram before they run.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return array<string, mixed>
     */
    public function previewChanges(DriverContract $driver, array $changes): array
    {
        return $this->assistant->preview($driver, $changes);
    }
}
