<?php

declare(strict_types=1);

namespace App\Services\Importer;

use App\Database\Contracts\DriverContract;
use App\Services\TableBrowser\TableCatalog;
use Generator;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Add the lines of a CSV file to a table, each column of the file going to the table column it is mapped to.
 * Every line is kept, or none: the first refused line undoes the import.
 */
class CsvImporter
{
    private const int CHUNK_SIZE = 500;

    public function __construct(protected TableCatalog $catalog) {}

    /**
     * @param  list<?string>  $mapping  the table column of each column of the file, null to leave it out
     * @param  array{delimiter: string, header: bool, emptyAsNull: bool, replace: bool}  $options
     * @return array{inserted: int, deleted: int, duration: int}
     *
     * @throws RuntimeException when the mapping names a column the table does not have, or the database refuses a line
     */
    public function import(DriverContract $driver, string $table, string $content, array $mapping, array $options): array
    {
        $startedAt = hrtime(true);
        $columns = $this->catalog->columnNames($driver, $table);
        $unknown = array_diff(array_filter($mapping), $columns);

        if ($unknown !== []) {
            throw new RuntimeException(__('imports.errors.unknown_columns', ['columns' => implode(', ', $unknown)]));
        }

        if (array_filter($mapping) === []) {
            throw new RuntimeException(__('imports.errors.no_mapping'));
        }

        if (count(array_filter($mapping)) !== count(array_unique(array_filter($mapping)))) {
            throw new RuntimeException(__('imports.errors.duplicate_mapping'));
        }

        $connection = $driver->getConnection();
        $counts = ['inserted' => 0, 'deleted' => 0];

        $connection->transaction(function () use ($connection, $table, $content, $mapping, $options, &$counts): void {
            if ($options['replace']) {
                $counts['deleted'] = $connection->table($table)->delete();
            }

            $chunk = [];
            $firstLine = $options['header'] ? 2 : 1;

            foreach ($this->lines($content, $options['delimiter'], $options['header']) as $values) {
                $row = [];

                foreach ($mapping as $index => $column) {
                    if ($column !== null) {
                        $value = $values[$index] ?? null;
                        $row[$column] = $options['emptyAsNull'] && $value === '' ? null : $value;
                    }
                }

                $chunk[] = $row;

                if (count($chunk) === self::CHUNK_SIZE) {
                    $this->insert($connection, $table, $chunk, $firstLine + $counts['inserted']);
                    $counts['inserted'] += count($chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->insert($connection, $table, $chunk, $firstLine + $counts['inserted']);
                $counts['inserted'] += count($chunk);
            }
        });

        return [...$counts, 'duration' => (int) ((hrtime(true) - $startedAt) / 1_000_000)];
    }

    /**
     * @param  list<array<string, ?string>>  $rows
     *
     * @throws RuntimeException
     */
    protected function insert(Connection $connection, string $table, array $rows, int $firstLine): void
    {
        try {
            $connection->table($table)->insert($rows);
        } catch (QueryException $exception) {
            $lastLine = $firstLine + count($rows) - 1;

            throw new RuntimeException(__('imports.errors.rows_refused', ['first' => $firstLine, 'last' => $lastLine, 'error' => $exception->errorInfo[2] ?? $exception->getMessage()]), 0, $exception);
        }
    }

    /**
     * The values of each non-empty line, the header line left out.
     *
     * @return Generator<int, list<?string>>
     */
    protected function lines(string $content, string $delimiter, bool $header): Generator
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $content);
        rewind($stream);

        try {
            $first = true;

            while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                if ($first && $header) {
                    $first = false;

                    continue;
                }

                $first = false;

                if ($values === [null]) {
                    continue;
                }

                yield $values;
            }
        } finally {
            fclose($stream);
        }
    }
}
