<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Services\TableBrowser\Data\TableView;

/**
 * The number of rows of a view. Counting a large table means reading all of it, so without any filter the
 * statistics of the engine are trusted instead, and the total is marked as estimated.
 */
class TableRowCounter
{
    public const int EXACT_COUNT_BELOW = 200_000;

    public function __construct(protected TableQuery $query) {}

    /**
     * @return array{total: int, estimated: bool}
     */
    public function count(DriverContract $driver, string $table, TableView $view): array
    {
        $estimate = $view->filters === [] ? $driver->estimatedRowCount($table) : null;

        if ($estimate !== null && $estimate >= self::EXACT_COUNT_BELOW) {
            return ['total' => $estimate, 'estimated' => true];
        }

        return ['total' => $this->query->build($driver, $table, $view, sorted: false)->count(), 'estimated' => false];
    }
}
