<?php

namespace App\Console\Commands\Chat\Tools;

use App\Support\TranslatableMessage;
use Illuminate\Support\Str;

/**
 * The chart the agent displays, drawn as horizontal bars whatever its type: one bar for each point and each series.
 */
class ChartRenderer extends ArtifactRenderer
{
    private const int PREVIEW_POINTS = 12;

    private const int MAX_LABEL_WIDTH = 24;

    private const int MAX_BAR_WIDTH = 50;

    /**
     * The color of each series, among the 256 colors of a terminal.
     */
    private const array COLORS = [39, 215, 114, 176, 221, 80];

    private const array PARTIAL_BLOCKS = ['', '▏', '▎', '▍', '▌', '▋', '▊', '▉'];

    public function title(array $arguments): string
    {
        return (string) ($arguments['title'] ?? __('ai.artifacts.chart_title', [
            'y' => implode(', ', (array) ($arguments['yColumns'] ?? [])),
            'x' => (string) ($arguments['xColumn'] ?? ''),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    protected function lines(array $arguments, mixed $result, int $width, bool $preview): array
    {
        $theme = $this->terminal->theme;
        $data = $this->artifacts->chart($this->driver(), $arguments);
        $series = array_values(array_map(strval(...), (array) ($arguments['yColumns'] ?? [])));
        $xColumn = (string) ($arguments['xColumn'] ?? '');
        $rows = $preview ? array_slice($data['rows'], 0, self::PREVIEW_POINTS) : $data['rows'];

        if ($rows === [] || $series === []) {
            return [$theme->muted(__('ai.artifacts.no_data'))];
        }

        $labels = array_map(fn (array $row): string => Str::limit((string) ($row[$xColumn] ?? ''), self::MAX_LABEL_WIDTH - 1, '…'), $rows);
        $values = array_map(fn (array $row): array => array_map(fn (string $column): float => (float) ($row[$column] ?? 0), $series), $rows);
        $texts = array_map(fn (array $point): array => array_map($this->number(...), $point), $values);

        $labelWidth = max(array_map(mb_strwidth(...), $labels));
        $textWidth = max(array_map(mb_strwidth(...), array_merge(...$texts)));
        $barWidth = max(min($width - $labelWidth - $textWidth - 4, self::MAX_BAR_WIDTH), 5);
        $highest = max([1e-9, ...array_map(abs(...), array_merge(...$values))]);

        $lines = count($series) > 1 ? [$this->legend($series), ''] : [];

        foreach ($rows as $position => $row) {
            foreach ($series as $index => $column) {
                $lines[] = $theme->muted(mb_str_pad($index === 0 ? $labels[$position] : '', $labelWidth)).' '
                    .$theme->color(mb_str_pad($this->bar(abs($values[$position][$index]) / $highest * $barWidth), $barWidth), $this->colorOf($index)).' '
                    .$texts[$position][$index];
            }
        }

        $hidden = count($data['rows']) - count($rows);

        return [
            ...$lines,
            ...($hidden > 0 || $data['truncated'] ? [$theme->muted(__('chat_cli.artifacts.more_points', ['count' => count($rows)]))] : []),
        ];
    }

    /**
     * @param  list<string>  $series
     */
    private function legend(array $series): string
    {
        return implode('  ', array_map(
            fn (string $column, int $index): string => $this->terminal->theme->color('■', $this->colorOf($index)).' '.$column,
            $series,
            array_keys($series),
        ));
    }

    /**
     * A bar of the given length, in columns: its last block is drawn in eighths, so two close values still differ.
     */
    private function bar(float $length): string
    {
        $eighths = (int) round($length * 8);

        return str_repeat('█', intdiv($eighths, 8)).self::PARTIAL_BLOCKS[$eighths % 8];
    }

    private function number(float $value): string
    {
        return TranslatableMessage::formatNumber($value, floor($value) === $value ? 'integer' : 'decimal');
    }

    private function colorOf(int $series): int
    {
        return self::COLORS[$series % count(self::COLORS)];
    }
}
