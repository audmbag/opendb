<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\Output\SqlHighlighter;
use App\Console\Commands\Chat\Output\Theme;
use Illuminate\Support\Str;

/**
 * A query run by the agent: the SQL in colors, then how many rows it returned. The rows themselves are left to the
 * answer of the agent, which would otherwise show them twice.
 */
class SqlToolRenderer extends ToolRenderer
{
    public function __construct(
        private readonly Theme $theme,
        private readonly SqlHighlighter $sqlHighlighter,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function call(array $arguments): array
    {
        $bindings = $arguments['bindings'] ?? [];

        return [
            ...explode("\n", $this->sqlHighlighter->highlight($this->sqlHighlighter->format((string) ($arguments['query'] ?? '')))),
            ...(blank($bindings) ? [] : [$this->theme->muted($this->short($bindings))]),
        ];
    }

    public function summary(mixed $result): ?string
    {
        $rows = $this->rows($result);

        return $rows === null ? parent::summary($result) : trans_choice('chat_cli.tools.rows', count($rows));
    }

    /**
     * The rows of a read query, which the tool answers as JSON, followed by a note when it cut them.
     *
     * @return list<array<string, mixed>>|null null when the answer is not a list of rows, like an error
     */
    private function rows(mixed $result): ?array
    {
        $rows = is_string($result) ? json_decode(Str::before($result, "\n\n("), true) : null;

        return is_array($rows) && array_is_list($rows) && array_all($rows, fn (mixed $row): bool => is_array($row)) ? $rows : null;
    }
}
