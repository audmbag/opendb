<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use App\Database\ReadOnlyQueryGuard;
use App\Database\SelectQuery;
use App\Services\TableBrowser\TableBrowserManager;
use Generator;
use PDO;
use Pdo\Mysql;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Every row of a SELECT of the editor, beyond the thousand the console shows, written as the export of a table.
 * The query is opened before the download starts, so that an error is reported instead of an empty file,
 * then its rows are read one by one, never all in memory.
 */
class QueryResultExporter
{
    public function __construct(private TableBrowserManager $tables) {}

    /**
     * Run the query and return what writes its rows, once the database accepted it.
     *
     * @return callable(resource): void
     *
     * @throws RuntimeException when the query is not a single SELECT, or the database refuses it
     */
    public function open(DriverContract $driver, string $sql, string $format): callable
    {
        if (! in_array($format, ['csv', 'json'], true)) {
            throw new RuntimeException("Format d'export inconnu : {$format}.");
        }

        $sql = ReadOnlyQueryGuard::assertExplainable($sql);
        $pdo = $driver->getConnection()->getPdo();

        // MySQL sends the whole result to PHP before the first row by default: it is read as it comes instead.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, false);
        }

        try {
            $statement = $pdo->query($sql);
        } catch (PDOException $exception) {
            throw new RuntimeException((string) ($exception->errorInfo[2] ?? $exception->getMessage()), previous: $exception);
        }

        $columns = array_map(
            fn (int $index): string => (string) ($statement->getColumnMeta($index)['name'] ?? "colonne {$index}"),
            range(0, max(0, $statement->columnCount() - 1)),
        );

        return function ($output) use ($format, $columns, $statement): void {
            $this->tables->exportRows($format, $columns, $this->rows($statement, $columns), $output);
        };
    }

    /**
     * @param  list<string>  $columns
     * @return Generator<int, array<string, mixed>>
     */
    private function rows(PDOStatement $statement, array $columns): Generator
    {
        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            yield array_combine($columns, array_map(SelectQuery::displayableValue(...), $row));
        }

        $statement->closeCursor();
    }
}
