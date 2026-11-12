<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use App\Database\SelectQuery;
use App\Services\QueryConsole\QueryConsoleManager;
use App\Services\QueryConsole\StatementParameters;
use RuntimeException;

/**
 * The rows of a SELECT beyond the first thousand the editor shows, page by page: the query is wrapped in a subquery
 * and read again in a read-only transaction, as the results of the chat are.
 */
class ResultPages
{
    public function __construct(private StatementParameters $parameters) {}

    /**
     * @param  array<string, ?string>  $bindings
     * @return list<list<mixed>> the rows of the page, their values in the order of the columns
     *
     * @throws RuntimeException when the query is not a single SELECT, or the database refuses it
     */
    public function rows(DriverContract $driver, string $sql, array $bindings, int $offset): array
    {
        $rows = new SelectQuery($driver, $sql, $this->parameters->bindingsFor($sql, $bindings))->rows(QueryConsoleManager::ROW_LIMIT, max(0, $offset));

        return array_map(array_values(...), $rows);
    }
}
