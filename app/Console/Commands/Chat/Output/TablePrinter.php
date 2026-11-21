<?php

namespace App\Console\Commands\Chat\Output;

use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Draws a table as lines of text, for the rows of a query as for a table written in Markdown.
 */
class TablePrinter
{
    /**
     * A longer value is cut, so one long text does not push the other columns off the screen.
     */
    private const int MAX_CELL_WIDTH = 40;

    /**
     * The room kept for the column counting those left out.
     */
    private const int MORE_COLUMN_WIDTH = 6;

    public function __construct(private readonly Theme $theme) {}

    /**
     * @param  list<string>  $headers
     * @param  list<array<int, scalar|null>>  $rows
     * @param  array<int, 'left'|'center'|'right'>  $alignments  by the position of the column
     * @param  int|null  $maxWidth  the last columns are left out, and counted in a last one, when the table is wider
     * @return list<string>
     */
    public function lines(array $headers, array $rows, array $alignments = [], ?int $maxWidth = null): array
    {
        [$headers, $rows] = $this->fitting($headers, array_map(array_values(...), $rows), $maxWidth);

        $output = new BufferedOutput(decorated: $this->theme->decorated);

        new Table($output)
            ->setStyle('box')
            ->setHeaders(array_map(fn (string $header): string => $this->escape($this->theme->bold($header)), $headers))
            ->setRows(array_map(
                fn (array $row): array => array_map(
                    fn (mixed $value, int $position): TableCell => $this->cell($value, $alignments[$position] ?? null),
                    array_values($row),
                    array_keys(array_values($row)),
                ),
                $rows,
            ))
            ->render();

        // The output ends its lines with PHP_EOL, which is "\r\n" on Windows.
        return preg_split('/\r?\n/', rtrim($output->fetch(), "\r\n"));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @return array{list<string>, list<list<mixed>>}
     */
    private function fitting(array $headers, array $rows, ?int $maxWidth): array
    {
        if ($maxWidth === null) {
            return [$headers, $rows];
        }

        $width = 1;
        $kept = 0;

        foreach ($headers as $position => $header) {
            $cells = array_map(fn (array $row): int => $this->theme->width($this->text($row[$position] ?? null)), $rows);
            $width += max($this->theme->width($header), ...$cells, ...[0]) + 3;

            if ($width > $maxWidth - ($position < count($headers) - 1 ? self::MORE_COLUMN_WIDTH : 0)) {
                break;
            }

            $kept++;
        }

        if ($kept === count($headers)) {
            return [$headers, $rows];
        }

        $kept = max($kept, 1);
        $more = '+'.(count($headers) - $kept);

        return [
            [...array_slice($headers, 0, $kept), $more],
            array_map(fn (array $row): array => [...array_slice($row, 0, $kept), '…'], $rows),
        ];
    }

    /**
     * A number is read more easily on the right, unless the table says where its column goes.
     */
    private function cell(mixed $value, ?string $alignment): TableCell
    {
        $text = $this->text($value);

        $alignment ??= is_int($value) || is_float($value) ? 'right' : 'left';

        return new TableCell($this->escape($text), ['style' => new TableCellStyle(['align' => $alignment])]);
    }

    private function text(mixed $value): string
    {
        return match (true) {
            $value === null => $this->theme->muted('NULL'),
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => $this->limit((string) $value),
            default => $this->limit((string) json_encode($value, JSON_UNESCAPED_UNICODE)),
        };
    }

    /**
     * A text already styled is kept whole: cutting it could leave a style open.
     */
    private function limit(string $text): string
    {
        $text = str_replace(["\r\n", "\n", "\r"], ' ', $text);

        return str_contains($text, "\e") ? $text : Str::limit($text, self::MAX_CELL_WIDTH, '…');
    }

    private function escape(string $text): string
    {
        return OutputFormatter::escape($text);
    }
}
