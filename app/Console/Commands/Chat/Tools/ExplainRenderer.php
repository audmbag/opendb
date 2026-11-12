<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\SqlHighlighter;
use App\Console\Commands\Chat\Output\TablePrinter;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Output\Theme;
use App\Services\QueryPlan\QueryPlanManager;
use App\Services\ToolArtifact\ToolArtifactManager;
use App\Support\TranslatableMessage;

/**
 * The execution plan the agent computed, drawn as a tree with the alerts of each step. The plan is the one stored when
 * the agent asked for it: it is never computed again.
 */
class ExplainRenderer extends ArtifactRenderer
{
    public function __construct(
        private readonly QueryPlanManager $queryPlans,
        Terminal $terminal,
        ChatSession $session,
        ToolArtifactManager $artifacts,
        TablePrinter $tablePrinter,
        SqlHighlighter $sqlHighlighter,
        WebChatLink $webChatLink,
    ) {
        parent::__construct($terminal, $session, $artifacts, $tablePrinter, $sqlHighlighter, $webChatLink);
    }

    public function title(array $arguments): string
    {
        return __('chat_cli.artifacts.plan');
    }

    /**
     * {@inheritdoc}
     */
    protected function lines(array $arguments, mixed $result, int $width, bool $preview): array
    {
        $planId = $this->resultData($result)['planId'] ?? null;
        $plan = is_string($planId) && $this->session->conversation !== null
            ? $this->queryPlans->find($this->session->conversation->id, $planId)
            : null;

        if ($plan === null) {
            return [$this->terminal->theme->muted(__('ai.artifacts.unavailable'))];
        }

        return $this->children($plan['nodes'], null, '');
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private function children(array $nodes, int|string|null $parentId, string $indent): array
    {
        $theme = $this->terminal->theme;
        $children = array_values(array_filter($nodes, fn (array $node): bool => $node['parentId'] === $parentId));
        $lines = [];

        foreach ($children as $position => $node) {
            $last = $position === count($children) - 1;
            $branch = $parentId === null ? '' : ($last ? '└─ ' : '├─ ');
            $below = $parentId === null ? '' : ($last ? '   ' : '│  ');

            $facts = array_filter([
                $node['subtitle'] ?? null,
                is_numeric($node['estimatedRows'] ?? null) ? trans_choice('chat_cli.tools.rows', (int) $node['estimatedRows'], ['count' => TranslatableMessage::formatNumber((float) $node['estimatedRows'], 'rows')]) : null,
                is_numeric($node['costShare'] ?? null) ? TranslatableMessage::formatNumber((float) $node['costShare'], 'percent') : null,
            ]);

            $lines[] = $theme->muted($indent.$branch).$theme->bold((string) $node['label']).($facts === [] ? '' : $theme->muted(' · '.implode(' · ', $facts)));

            foreach ((array) ($node['alerts'] ?? []) as $alert) {
                $text = Theme::WARNING.' '.TranslatableMessage::text($alert['message'] ?? $alert['code'] ?? '');
                $lines[] = $theme->muted($indent.$below).(($alert['severity'] ?? null) === 'danger' ? $theme->danger($text) : $theme->warning($text));
            }

            array_push($lines, ...$this->children($nodes, $node['id'], $indent.$below));
        }

        return $lines;
    }
}
