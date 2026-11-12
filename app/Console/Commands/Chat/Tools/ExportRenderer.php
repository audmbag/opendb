<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Theme;
use Illuminate\Support\Number;

/**
 * An export of the database written by the agent: where its file is, since a terminal has nothing to download.
 */
class ExportRenderer extends ToolRenderer
{
    public function __construct(
        private readonly Theme $theme,
        private readonly ChatSession $session,
    ) {}

    public function summary(mixed $result): ?string
    {
        $bytes = $this->decoded($result)['bytes'] ?? null;

        return is_int($bytes) ? Number::fileSize($bytes) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function details(mixed $result, array $arguments = []): array
    {
        $path = $this->decoded($result)['path'] ?? null;

        return is_string($path) ? [$this->theme->accent($this->session->project->path.DIRECTORY_SEPARATOR.$path)] : [];
    }
}
