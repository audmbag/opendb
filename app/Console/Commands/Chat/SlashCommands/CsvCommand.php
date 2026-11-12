<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Ai\Tools\Database\ShowQueryResults;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Tools\ArtifactCatalog;
use App\Services\ToolArtifact\ToolArtifactManager;
use App\Support\TranslatableMessage;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Exports the rows of a table of the conversation to a CSV file, in the exports directory of the project.
 */
class CsvCommand extends SlashCommand
{
    public function __construct(
        private readonly ArtifactCatalog $artifacts,
        private readonly ToolArtifactManager $artifactManager,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'csv';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $artifact = $this->artifacts->pick($session, $arguments, ShowQueryResults::class);

        if ($artifact === null) {
            return;
        }

        try {
            $export = $this->artifactManager->exportCsv($session->project->workspaceOf($session->user), $session->project->driver_config->agentDriverFor($session->user), $artifact->arguments);
        } catch (QueryException|RuntimeException $exception) {
            $this->terminal->failure($exception->getPrevious()?->getMessage() ?? $exception->getMessage());

            return;
        }

        $this->terminal->success(trans_choice('chat_cli.artifacts.csv_written', $export['rows'], ['count' => TranslatableMessage::formatNumber($export['rows']), 'path' => $export['path']]));
    }
}
