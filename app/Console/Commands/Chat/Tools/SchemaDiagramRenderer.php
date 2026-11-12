<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\Output\Theme;

/**
 * The schema diagram the agent displays. A terminal cannot draw it: the tables it holds are listed, with the link to
 * the web chat where the diagram itself is drawn.
 */
class SchemaDiagramRenderer extends ToolRenderer
{
    public function __construct(
        private readonly Theme $theme,
        private readonly WebChatLink $webChatLink,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function call(array $arguments): array
    {
        $table = $arguments['focusedTable']['tableName'] ?? null;

        return is_string($table) ? [$this->theme->bold(__('ai.artifacts.schema_of', ['table' => $table]))] : [];
    }

    public function summary(mixed $result): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function details(mixed $result, array $arguments = []): array
    {
        $decoded = $this->decoded($result);
        $tables = array_filter([$decoded['focusedTable'] ?? null, ...(array) ($decoded['displayedTables'] ?? [])]);

        return [
            ...($tables === [] ? [] : [implode($this->theme->muted(' · '), array_unique(array_map(strval(...), $tables)))]),
            ...$this->webChatLink->lines('chat_cli.artifacts.diagram_hint'),
        ];
    }
}
