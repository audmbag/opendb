<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Contracts\DriverContract;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Show or run the phases of a change of structure.
 *
 * PostgreSQL changes its structure in a transaction: a refused statement undoes the others. MySQL and MariaDB commit
 * each one at once, and SQLite rebuilds a table only outside a transaction, where it can set its foreign keys aside.
 */
class TableDesignApplier
{
    /**
     * Every statement the phases would run. Where the structure can be changed in a transaction, each phase runs
     * in one that is always undone, so the next one is compiled from the structure it will really find.
     *
     * @param  list<Closure(): list<string>>  $phases
     * @return list<string>
     *
     * @throws TableDesignFailedException
     */
    public function preview(DriverContract $driver, array $phases): array
    {
        $connection = $driver->getConnection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return array_merge(...array_map(fn (Closure $phase): array => $phase(), $phases));
        }

        return $this->withoutForeignKeysOnSqlite($connection, function () use ($connection, $phases): array {
            $connection->beginTransaction();

            try {
                return $this->run($connection, $phases);
            } finally {
                $connection->rollBack();
            }
        });
    }

    /**
     * @param  list<Closure(): list<string>>  $phases
     * @return list<string> the statements that ran
     *
     * @throws TableDesignFailedException
     */
    public function apply(DriverContract $driver, array $phases): array
    {
        $connection = $driver->getConnection();

        try {
            return $connection->getDriverName() === 'pgsql'
                ? $connection->transaction(fn (): array => $this->run($connection, $phases))
                : $this->run($connection, $phases);
        } finally {
            $driver->forgetSchema();
        }
    }

    /**
     * @param  list<Closure(): list<string>>  $phases
     * @return list<string>
     *
     * @throws TableDesignFailedException
     */
    protected function run(Connection $connection, array $phases): array
    {
        $executed = [];

        foreach ($phases as $phase) {
            try {
                $statements = $phase();
            } catch (QueryException $exception) {
                throw new TableDesignFailedException('', (string) ($exception->errorInfo[2] ?? $exception->getMessage()), $exception);
            }

            foreach ($statements as $statement) {
                try {
                    $connection->statement($statement);
                } catch (QueryException $exception) {
                    throw new TableDesignFailedException($statement, (string) ($exception->errorInfo[2] ?? $exception->getMessage()), $exception);
                }

                $executed[] = $statement;
            }
        }

        return $executed;
    }

    /**
     * In a transaction SQLite ignores the switch of its foreign keys: they are set aside before it starts.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function withoutForeignKeysOnSqlite(Connection $connection, Closure $callback): mixed
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return $callback();
        }

        $enabled = (bool) $connection->scalar('pragma foreign_keys');
        $connection->statement('pragma foreign_keys = 0');

        try {
            return $callback();
        } finally {
            if ($enabled) {
                try {
                    $connection->statement('pragma foreign_keys = 1');
                } catch (Throwable) {
                    // Restored by the next connection.
                }
            }
        }
    }
}
