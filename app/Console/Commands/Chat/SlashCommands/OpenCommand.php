<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Tools\ArtifactCatalog;

/**
 * Opens an artifact of the conversation whole: all the pages of a table, every point of a chart.
 */
class OpenCommand extends SlashCommand
{
    public function __construct(private readonly ArtifactCatalog $artifacts) {}

    public function name(): string
    {
        return 'open';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $this->artifacts->pick($session, $arguments)?->open();
    }
}
