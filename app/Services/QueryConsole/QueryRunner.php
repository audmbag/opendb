<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

use App\Database\AdminStatementGuard;
use App\Database\Contracts\DriverContract;
use App\Database\SelectQuery;
use App\Services\Importer\SqlStatementSplitter;
use Illuminate\Database\QueryException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Run the statements typed in the console, one after the other, until one fails. A statement that returns rows
 * gives its first rows, any other the number of rows it changed.
 */
class QueryRunner
{
    /**
     * The statements after which the cached schema no longer describes the database.
     */
    private const string SCHEMA_CHANGE = '/^\s*(?:CREATE|ALTER|DROP|RENAME|TRUNCATE)\b/i';

    public function __construct(protected SqlStatementSplitter $splitter) {}

    /**
     * @return array{results: list<array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}>, error: ?array{index: int, statement: string, message: string}}
     *
     * @throws RuntimeException when there is nothing to run
     */
    public function run(DriverContract $driver, string $sql, int $limit): array
    {
        $connection = $driver->getConnection();
        $statements = $this->splitter->split($sql, $connection->getDriverName());

        if ($statements === []) {
            throw new RuntimeException('Saisissez une instruction SQL à exécuter.');
        }

        $results = [];
        $schemaChanged = false;

        try {
            foreach ($statements as $index => $statement) {
                try {
                    AdminStatementGuard::assertNotAdministrative($statement);
                    $results[] = $this->execute($connection->getPdo(), $statement, $limit);
                    $schemaChanged = $schemaChanged || preg_match(self::SCHEMA_CHANGE, $statement) === 1;
                } catch (PDOException|QueryException|RuntimeException $exception) {
                    return [
                        'results' => $results,
                        'error' => [
                            'index' => $index + 1,
                            'statement' => $statement,
                            'message' => $exception instanceof PDOException || $exception instanceof QueryException
                                ? (string) ($exception->errorInfo[2] ?? $exception->getMessage())
                                : $exception->getMessage(),
                        ],
                    ];
                }
            }
        } finally {
            if ($schemaChanged) {
                $driver->forgetSchema();
            }
        }

        return ['results' => $results, 'error' => null];
    }

    /**
     * @return array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}
     */
    protected function execute(PDO $pdo, string $statement, int $limit): array
    {
        $startedAt = hrtime(true);
        $query = $pdo->query($statement);
        $columnCount = $query->columnCount();

        if ($columnCount === 0) {
            return [
                'statement' => $statement,
                'columns' => null,
                'rows' => [],
                'truncated' => false,
                'affected' => $query->rowCount(),
                'duration' => $this->since($startedAt),
            ];
        }

        $columns = array_map(fn (int $index): string => (string) ($query->getColumnMeta($index)['name'] ?? "colonne {$index}"), range(0, $columnCount - 1));
        $rows = [];
        $truncated = false;

        while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
            if (count($rows) === $limit) {
                $truncated = true;

                break;
            }

            $rows[] = array_map(SelectQuery::displayableValue(...), $row);
        }

        $query->closeCursor();

        return [
            'statement' => $statement,
            'columns' => $columns,
            'rows' => $rows,
            'truncated' => $truncated,
            'affected' => null,
            'duration' => $this->since($startedAt),
        ];
    }

    protected function since(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
