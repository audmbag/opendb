<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

use App\Database\AdminStatementGuard;
use App\Database\Contracts\DriverContract;
use App\Database\ReadOnlyQueryGuard;
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

    public function __construct(
        protected SqlStatementSplitter $splitter,
        protected StatementParameters $parameters,
    ) {}

    /**
     * @return array{results: list<array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}>, error: ?array{index: int, statement: string, message: string}, transaction: ?string}
     *
     * @throws RuntimeException when there is nothing to run, or the database could not undo the statements the mode would undo
     */
    public function run(DriverContract $driver, string $sql, int $limit, RunOptions $options = new RunOptions): array
    {
        $connection = $driver->getConnection();
        $statements = $this->splitter->split($sql, $connection->getDriverName());

        if ($statements === []) {
            throw new RuntimeException(__('tables.console.errors.empty'));
        }

        // Read-only, nothing is kept either: the guard is not the only protection.
        $mode = $options->readOnly ? TransactionMode::DryRun : $options->mode;
        $inTransaction = $mode !== TransactionMode::Auto;

        if ($inTransaction && ! $driver->canRollBack($statements)) {
            throw new RuntimeException(__('tables.console.errors.cannot_roll_back'));
        }

        $pdo = $connection->getPdo();

        if ($options->beforeRun !== null) {
            ($options->beforeRun)($driver);
        }

        if ($inTransaction) {
            $pdo->beginTransaction();
        }

        $results = [];
        $error = null;
        $schemaChanged = false;

        try {
            foreach ($statements as $index => $statement) {
                try {
                    AdminStatementGuard::assertNotAdministrative($statement);
                    $driver->assertQueryAllowed($statement);

                    if ($options->readOnly && ! ReadOnlyQueryGuard::isReadOnly($statement)) {
                        throw new RuntimeException(__('tables.console.errors.read_only'));
                    }

                    $results[] = $this->execute($pdo, $statement, $limit, $this->parameters->bindingsFor($statement, $options->bindings));
                    $schemaChanged = $schemaChanged || preg_match(self::SCHEMA_CHANGE, $statement) === 1;
                } catch (PDOException|QueryException|RuntimeException $exception) {
                    $error = [
                        'index' => $index + 1,
                        'statement' => $statement,
                        'message' => $exception instanceof PDOException || $exception instanceof QueryException
                            ? (string) ($exception->errorInfo[2] ?? $exception->getMessage())
                            : $exception->getMessage(),
                    ];

                    break;
                }
            }
        } finally {
            $transaction = $inTransaction ? $this->end($pdo, $mode === TransactionMode::Atomic && $error === null) : null;

            // Undone, the statements left the structure of the database as it was.
            if ($schemaChanged && $transaction !== 'rolled_back') {
                $driver->forgetSchema();
            }
        }

        return ['results' => $results, 'error' => $error, 'transaction' => $transaction];
    }

    /**
     * Keep or undo the statements of the transaction, the one a failed statement left open included.
     */
    protected function end(PDO $pdo, bool $commit): string
    {
        if (! $pdo->inTransaction()) {
            return 'rolled_back';
        }

        $commit ? $pdo->commit() : $pdo->rollBack();

        return $commit ? 'committed' : 'rolled_back';
    }

    /**
     * @param  array<string, ?string>  $bindings  the values of the parameters of the statement: without any, it is sent as it is
     * @return array{statement: string, columns: ?list<string>, rows: list<list<mixed>>, truncated: bool, affected: ?int, duration: int}
     */
    protected function execute(PDO $pdo, string $statement, int $limit, array $bindings = []): array
    {
        $startedAt = hrtime(true);

        if ($bindings === []) {
            $query = $pdo->query($statement);
        } else {
            $query = $pdo->prepare($statement);
            $query->execute($bindings);
        }
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

        $columns = array_map(fn (int $index): string => (string) ($query->getColumnMeta($index)['name'] ?? __('tables.console.column_number', ['number' => $index])), range(0, $columnCount - 1));
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
