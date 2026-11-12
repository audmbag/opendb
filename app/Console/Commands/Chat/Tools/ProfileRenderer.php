<?php

namespace App\Console\Commands\Chat\Tools;

use App\Support\TranslatableMessage;
use Illuminate\Support\Str;

/**
 * The profile of a table the agent displays: one row for each column, and its most frequent values once opened.
 */
class ProfileRenderer extends ArtifactRenderer
{
    private const int PREVIEW_COLUMNS = 12;

    private const int VALUE_WIDTH = 18;

    public function title(array $arguments): string
    {
        return __('ai.artifacts.profile_of', ['table' => (string) ($arguments['tableName'] ?? '')]);
    }

    /**
     * {@inheritdoc}
     */
    protected function lines(array $arguments, mixed $result, int $width, bool $preview): array
    {
        $theme = $this->terminal->theme;
        $profile = $this->artifacts->profile($this->driver(), $arguments);
        $columns = $preview ? array_slice($profile['columns'], 0, self::PREVIEW_COLUMNS) : $profile['columns'];
        $hidden = count($profile['columns']) - count($columns);

        return [
            $theme->muted(__('chat_cli.artifacts.profile_summary', [
                'rows' => TranslatableMessage::formatNumber($profile['totalRows']),
                'sample' => TranslatableMessage::formatNumber($profile['sampleSize']),
            ])),
            ...$this->tablePrinter->lines(
                [__('chat_cli.artifacts.column'), 'Type', __('chat_cli.artifacts.key'), 'NULL', __('chat_cli.artifacts.distinct'), 'Min', 'Max'],
                array_map(fn (array $column): array => [
                    $column['name'],
                    $column['type'],
                    $column['key'] ?? '',
                    TranslatableMessage::formatNumber($column['nullRatio'], 'percent'),
                    $column['distinctCount'] ?? '',
                    $this->short($column['min']),
                    $this->short($column['max']),
                ], $columns),
                maxWidth: $width,
            ),
            ...($hidden > 0 ? [$theme->muted(trans_choice('chat_cli.artifacts.more_columns', $hidden))] : []),
            ...($preview ? [] : $this->topValues($profile['columns'])),
        ];
    }

    /**
     * @param  list<array{name: string, topValues: list<array{value: mixed, count: int}>}>  $columns
     * @return list<string>
     */
    private function topValues(array $columns): array
    {
        $theme = $this->terminal->theme;
        $lines = ['', $theme->bold(__('ai.profile.top_values'))];

        foreach ($columns as $column) {
            if ($column['topValues'] === []) {
                continue;
            }

            $lines[] = $theme->muted($column['name'].' : ').implode($theme->muted(' · '), array_map(
                fn (array $top): string => $this->short($top['value']).$theme->muted(' ×'.$top['count']),
                $column['topValues'],
            ));
        }

        return $lines;
    }

    protected function short(mixed $value): string
    {
        return $value === null ? '' : Str::limit(parent::short($value), self::VALUE_WIDTH, '…');
    }
}
