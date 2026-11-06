<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use App\Database\Contracts\DriverContract;
use App\Services\TableBrowser\Data\TableChange;
use Illuminate\Database\QueryException;

/**
 * Apply the changes of the user in one transaction: all of them are kept, or none if one fails.
 */
class TableChangesApplier
{
    public function __construct(protected TableChangeStatements $statements) {}

    /**
     * @param  list<TableChange>  $changes
     * @return array{inserted: int, updated: int, deleted: int}
     *
     * @throws TableChangeFailedException
     */
    public function apply(DriverContract $driver, string $table, array $changes): array
    {
        $connection = $driver->getConnection();
        $counts = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];

        $connection->transaction(function () use ($driver, $connection, $table, $changes, &$counts): void {
            foreach ($changes as $index => $change) {
                ['sql' => $sql, 'bindings' => $bindings] = $this->statements->compile($driver, $table, $change);

                try {
                    $affected = match ($change->type) {
                        'insert' => (int) $connection->insert($sql, $bindings),
                        default => $connection->affectingStatement($sql, $bindings),
                    };
                } catch (QueryException $exception) {
                    throw new TableChangeFailedException($index, (string) ($exception->errorInfo[2] ?? $exception->getMessage()), $exception);
                }

                // MySQL counts as affected only the rows whose values really change: an update keeping them is not an error.
                if ($affected === 0 && ($change->type === 'delete' || ! $this->exists($driver, $table, $change))) {
                    throw TableChangeFailedException::rowMissing($index);
                }

                $counts[match ($change->type) {
                    'insert' => 'inserted',
                    'update' => 'updated',
                    'delete' => 'deleted',
                }]++;
            }
        });

        return $counts;
    }

    protected function exists(DriverContract $driver, string $table, TableChange $change): bool
    {
        return $this->statements->rowQuery($driver, $table, $change->key)->exists();
    }
}
