<?php

declare(strict_types=1);

namespace App\Services\Exporter;

use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Exporter\Data\ExportSource;
use Generator;

/**
 * Lit les lignes d'une table par lots, pour que la mémoire reste stable quelle que soit sa taille.
 */
class TableRowReader
{
    const int CHUNK_SIZE = 1000;

    /**
     * Lots de CHUNK_SIZE lignes, triées par clé primaire quand la table en a une.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function chunks(ExportSource $source, TableSchemaData $table): Generator
    {
        $quote = $source->dialect->quoteIdentifier(...);
        $orderBy = array_filter($table->columns, fn (ColumnSchemaData $column): bool => $column->isPrimary)
            |> (fn (array $columns): array => array_map(fn (ColumnSchemaData $column): string => $quote($column->name), $columns))
            |> (fn (array $columns): string => $columns === [] ? '' : ' ORDER BY '.implode(', ', $columns));
        $offset = 0;

        do {
            $rows = $source->connection->select(sprintf('SELECT * FROM %s%s LIMIT %d OFFSET %d', $quote($table->name), $orderBy, self::CHUNK_SIZE, $offset))
                |> (fn (array $rows): array => array_map(fn (object $row): array => (array) $row, $rows));

            if ($rows !== []) {
                yield $rows;
            }

            $offset += count($rows);
        } while (count($rows) === self::CHUNK_SIZE);
    }

    /**
     * @return list<string>
     */
    public function columnNames(TableSchemaData $table): array
    {
        return array_map(fn (ColumnSchemaData $column): string => $column->name, $table->columns);
    }
}
