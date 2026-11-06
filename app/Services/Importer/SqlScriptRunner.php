<?php

declare(strict_types=1);

namespace App\Services\Importer;

use App\Database\AdminStatementGuard;
use App\Database\Contracts\DriverContract;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

/**
 * Run the statements of a script one after the other, as the import mode says to handle a failure.
 */
class SqlScriptRunner
{
    /**
     * The statements a dump uses to frame its own transaction: in a transaction of the import, they would end it early.
     */
    private const string TRANSACTION_CONTROL = '/^(?:BEGIN(?:\s+(?:TRANSACTION|WORK|DEFERRED|IMMEDIATE|EXCLUSIVE))?|START\s+TRANSACTION|COMMIT(?:\s+WORK)?|END(?:\s+TRANSACTION)?|ROLLBACK(?:\s+WORK)?)\s*$/i';

    private const int EXCERPT_LENGTH = 300;

    private const int REPORTED_FAILURES = 50;

    /**
     * @param  list<string>  $statements
     * @return array{statements: int, executed: int, skipped: int, failed: int, failures: list<array{index: int, statement: string, message: string}>, kept: bool, duration: int}
     */
    public function run(DriverContract $driver, array $statements, ImportMode $mode): array
    {
        $connection = $driver->getConnection();
        $startedAt = hrtime(true);
        $report = ['statements' => count($statements), 'executed' => 0, 'skipped' => 0, 'failed' => 0, 'failures' => [], 'kept' => true];

        if ($mode === ImportMode::Transaction) {
            $connection->beginTransaction();
        }

        try {
            foreach ($statements as $index => $statement) {
                if ($mode === ImportMode::Transaction && preg_match(self::TRANSACTION_CONTROL, $statement)) {
                    $report['skipped']++;

                    continue;
                }

                try {
                    AdminStatementGuard::assertNotAdministrative($statement);
                    $connection->unprepared($statement);
                    $report['executed']++;
                } catch (QueryException|RuntimeException $exception) {
                    $report['failed']++;

                    if (count($report['failures']) < self::REPORTED_FAILURES) {
                        $report['failures'][] = [
                            'index' => $index + 1,
                            'statement' => mb_strimwidth($statement, 0, self::EXCERPT_LENGTH, '…'),
                            'message' => $exception instanceof QueryException ? (string) ($exception->errorInfo[2] ?? $exception->getMessage()) : $exception->getMessage(),
                        ];
                    }

                    if ($mode !== ImportMode::Continue) {
                        break;
                    }
                }
            }

            if ($mode === ImportMode::Transaction) {
                if ($report['failed'] > 0) {
                    $this->rollBack($connection);
                    $report['kept'] = false;
                } else {
                    $connection->commit();
                }
            }
        } catch (Throwable $exception) {
            if ($mode === ImportMode::Transaction) {
                $this->rollBack($connection);
            }

            throw $exception;
        } finally {
            $driver->forgetSchema();
        }

        return [...$report, 'duration' => (int) ((hrtime(true) - $startedAt) / 1_000_000)];
    }

    /**
     * A statement that ended the transaction itself (a change of structure on MySQL) leaves nothing to roll back.
     */
    protected function rollBack(Connection $connection): void
    {
        try {
            $connection->rollBack();
        } catch (Throwable) {
            // Nothing left to undo.
        }
    }
}
