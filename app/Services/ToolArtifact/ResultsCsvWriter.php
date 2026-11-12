<?php

namespace App\Services\ToolArtifact;

use App\Database\SelectQuery;

/**
 * Writes the rows of a query as CSV, with its columns on the first line.
 */
class ResultsCsvWriter
{
    /**
     * @param  resource  $output  where the CSV is written: the body of a response, or a file
     * @return int the number of rows written
     */
    public function write(SelectQuery $query, mixed $output, int $maxRows, ?string $sort = null, string $direction = 'asc'): int
    {
        $rows = $query->rows($maxRows, 0, $sort, $direction);

        fputcsv($output, $rows === [] ? $query->columns() : array_keys($rows[0]), escape: '');

        foreach ($rows as $row) {
            fputcsv($output, array_map(fn (mixed $value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row), escape: '');
        }

        return count($rows);
    }
}
