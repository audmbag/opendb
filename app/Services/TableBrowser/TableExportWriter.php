<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Services\TableBrowser\Data\TableView;
use Generator;
use RuntimeException;

/**
 * Write every row of a view as CSV or JSON, chunk by chunk, so that a large table never sits in memory.
 */
class TableExportWriter
{
    public const array FORMATS = ['csv', 'json'];

    public function __construct(
        protected TableRowsReader $rows,
        protected TableCatalog $catalog,
    ) {}

    /**
     * @param  resource  $output
     *
     * @throws RuntimeException when the format is unknown.
     */
    public function write(DriverContract $driver, string $table, TableView $view, string $format, $output): void
    {
        $this->writeRows($format, $this->catalog->columnNames($driver, $table), $this->rowsOf($driver, $table, $view), $output);
    }

    /**
     * Write rows read one by one, like the rows of a query of the SQL editor, as CSV or JSON.
     *
     * @param  list<string>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  resource  $output
     *
     * @throws RuntimeException when the format is unknown.
     */
    public function writeRows(string $format, array $columns, iterable $rows, $output): void
    {
        match ($format) {
            'csv' => $this->csv($columns, $rows, $output),
            'json' => $this->json($rows, $output),
            default => throw new RuntimeException("Format d'export inconnu : {$format}."),
        };
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    protected function rowsOf(DriverContract $driver, string $table, TableView $view): Generator
    {
        foreach ($this->rows->chunks($driver, $table, $view) as $rows) {
            foreach ($rows as $row) {
                yield $row;
            }
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @param  resource  $output
     */
    protected function csv(array $columns, iterable $rows, $output): void
    {
        fputcsv($output, $columns, escape: '');

        foreach ($rows as $row) {
            fputcsv($output, array_map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : json_encode($value), array_values($row)), escape: '');
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  resource  $output
     */
    protected function json(iterable $rows, $output): void
    {
        $first = true;

        fwrite($output, '[');

        foreach ($rows as $row) {
            fwrite($output, ($first ? "\n  " : ",\n  ").json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
            $first = false;
        }

        fwrite($output, $first ? ']' : "\n]");
    }
}
