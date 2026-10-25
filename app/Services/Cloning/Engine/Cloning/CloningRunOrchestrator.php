<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Audit\CloningAuditLog;
use App\Services\Cloning\Engine\Data\Cloning\CloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\CloningOptionsData;
use App\Services\Cloning\Engine\Data\Cloning\ColumnCloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingConfigData;
use App\Services\Cloning\Engine\Data\Cloning\RunResultData;
use App\Services\Cloning\Engine\Data\Cloning\TableCloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\TableRunResultData;
use App\Services\Cloning\Engine\Data\Cloning\TableRunStatus;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Database\DatabaseConnectionService;
use App\Services\Cloning\Engine\Enums\ClearMode;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use Illuminate\Support\Facades\DB;
use Throwable;

class CloningRunOrchestrator
{
    public function __construct(
        private readonly DatabaseConnectionService $connector,
        private readonly SchemaReplicator $replicator,
        private readonly DependencyResolver $resolver,
    ) {}

    public function run(
        CloningAuditLog $log,
        CloningConfigData $config,
        ConnectionData $source,
        ConnectionData $target,
        DatabaseSchemaData $sourceSchema,
        bool $skipSchema,
        array $skipTables,
        array $onlyTables,
        callable $onProgress,
        ?KeyRemappingService $keyRemapping = null,
        bool $breakOnFailure = false,
        ?callable $onTableStart = null,
    ): RunResultData {
        $start = microtime(true);
        $tableNames = array_map(static fn (TableCloningConfigData $t): string => $t->tableName, $config->tables);

        $explicitlyExcluded = [];

        if ($onlyTables !== []) {
            $explicitlyExcluded = array_values(array_filter($tableNames, static fn (string $t): bool => ! in_array($t, $onlyTables, true)));
        } else {
            $explicitlyExcluded = $skipTables;
        }

        $cascadeExclusions = $this->resolver->computeCascadeExclusions($sourceSchema, $explicitlyExcluded, $tableNames);

        $remaining = array_values(array_filter(
            $tableNames,
            static fn (string $t): bool => ! in_array($t, $explicitlyExcluded, true) && ! in_array($t, $cascadeExclusions, true)
        ));

        $sortedTables = $this->resolver->sort($sourceSchema, $remaining);

        $schemaFailures = [];

        if (! $skipSchema) {
            $schemaFailures = $this->replicator->replicate(
                $source,
                $target,
                $sourceSchema,
                $sortedTables,
                $config->options->enforceColumnTypes,
                $config->options->dropUnknownTables,
                $config->options->dropExtraColumns,
            );

            if ($schemaFailures === []) {
                $log->info('schema_replicated', ['tables' => $sortedTables]);
            } else {
                foreach ($schemaFailures as $failedTable => $errorMsg) {
                    $log->error('schema_table_failed', ['table' => $failedTable, 'error' => $errorMsg]);
                }
            }
        }

        $engine = new AnonymizationEngine($config->options->fakerLocale);

        $tableResults = [];
        $totalRows = 0;
        $totalSkipped = 0;
        $success = true;

        foreach ($explicitlyExcluded as $tableName) {
            $reason = $onlyTables !== [] ? 'excluded by --only filter' : 'explicitly excluded via --skip';
            $tableResults[] = new TableRunResultData($tableName, TableRunStatus::SkippedByFlag, 0, 0, 0.0, $reason);
            $log->info('table_skipped_by_flag', ['table' => $tableName, 'reason' => $reason]);
        }

        foreach ($cascadeExclusions as $tableName) {
            $reason = 'excluded due to foreign key dependency';
            $tableResults[] = new TableRunResultData($tableName, TableRunStatus::SkippedByCascade, 0, 0, 0.0, $reason);
            $log->info('table_skipped_by_cascade', ['table' => $tableName, 'reason' => $reason]);
        }

        foreach ($sortedTables as $tableName) {
            $tableConfig = $config->getTable($tableName);

            if (! $tableConfig instanceof TableCloningConfigData) {
                continue;
            }

            if (array_key_exists($tableName, $schemaFailures)) {
                $reason = $schemaFailures[$tableName];
                $tableResults[] = new TableRunResultData($tableName, TableRunStatus::SkippedBySchemaFailure, 0, 0, 0.0, $reason);
                $log->warning('table_skipped_schema_failure', ['table' => $tableName, 'reason' => $reason]);
                $success = false;
                ($onProgress)($tableName, TableRunStatus::SkippedBySchemaFailure, 0, 0, []);

                if ($breakOnFailure) {
                    break;
                }

                continue;
            }

            if (! $sourceSchema->hasTable($tableName)) {
                $tableResults[] = new TableRunResultData($tableName, TableRunStatus::NotFound, 0, 0, 0.0, null);
                $log->warning('table_not_found', ['table' => $tableName]);
                ($onProgress)($tableName, TableRunStatus::NotFound, 0, 0, []);

                continue;
            }

            if ($onTableStart !== null) {
                $onTableStart($tableName);
            }

            $tableStart = microtime(true);
            $sourceTable = $sourceSchema->getTable($tableName);
            $pkColumns = $sourceTable instanceof TableSchemaData
            ? array_values(array_map(
                static fn (ColumnSchemaData $c): string => $c->name,
                array_filter($sourceTable->columns, static fn (ColumnSchemaData $c): bool => $c->isPrimary)
            ))
            : [];

            [$rows, $skipped, $failed, $reason, $skippedRows] = $this->transferTable(
                $log,
                $config->options,
                $tableConfig,
                $source,
                $target,
                $pkColumns,
                $engine,
                $keyRemapping,
                $config->keyRemapping,
            );

            $tableDuration = microtime(true) - $tableStart;

            $status = $failed ? TableRunStatus::Failed : TableRunStatus::Transferred;

            if ($failed) {
                $success = false;
                $log->error('table_transfer_failed', ['table' => $tableName, 'reason' => $reason]);
            } else {
                $log->info('table_transferred', ['table' => $tableName, 'rows' => $rows, 'skipped' => $skipped]);
            }

            $tableResults[] = new TableRunResultData($tableName, $status, $rows, $skipped, $tableDuration, $reason);
            $totalRows += $rows;
            $totalSkipped += $skipped;
            ($onProgress)($tableName, $status, $rows, $skipped, $skippedRows);

            if ($failed && $breakOnFailure) {
                break;
            }

            if (! $failed && $sourceTable instanceof TableSchemaData) {
                $pkColumn = $this->findIntegerPkColumn($target, $sourceTable);
                if ($pkColumn !== null) {
                    try {
                        $this->replicator->correctAutoIncrement($target, $tableName, $pkColumn);
                    } catch (Throwable $e) {
                        $log->warning('auto_increment_correction_failed', ['table' => $tableName, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        $duration = microtime(true) - $start;

        return new RunResultData(
            success: $success,
            tables: $tableResults,
            totalRows: $totalRows,
            skippedRows: $totalSkipped,
            durationSeconds: $duration,
            failureReason: $success ? null : 'One or more tables failed to transfer',
        );
    }

    private function findIntegerPkColumn(ConnectionData $target, TableSchemaData $table): ?string
    {
        if (! in_array($target->type, [DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB], true)) {
            return null;
        }

        $pkColumns = array_values(array_filter($table->columns, static fn (ColumnSchemaData $c): bool => $c->isPrimary));

        if (count($pkColumns) !== 1) {
            return null;
        }

        $intTypes = ['int', 'bigint', 'mediumint', 'smallint', 'tinyint', 'integer'];

        if (! in_array(strtolower($pkColumns[0]->type), $intTypes, true)) {
            return null;
        }

        return $pkColumns[0]->name;
    }

    private function transferTable(
        CloningAuditLog $log,
        CloningOptionsData $options,
        TableCloningConfigData $tableConfig,
        ConnectionData $source,
        ConnectionData $target,
        array $pkColumns,
        AnonymizationEngine $engine,
        ?KeyRemappingService $keyRemapping = null,
        ?KeyRemappingConfigData $keyRemappingConfig = null,
    ): array {
        $sourceConn = $this->connector->open($source);
        $targetConn = $this->connector->open($target);

        $rows = 0;
        $skipped = 0;
        $offset = 0;
        $chunkSize = $options->chunkSize;
        $firstInsertError = null;

        $skippedRows = [];

        try {
            if ($options->disableForeignKeyChecks) {
                $this->disableFkChecks($targetConn, $target);
            }

            if ($tableConfig->rows->clear !== ClearMode::None) {
                $this->clearTable($targetConn, $tableConfig->tableName, $tableConfig->rows->clear, $target);
            }

            do {

                $chunk = DB::connection($sourceConn)->select(
                    $this->buildChunkQuery($tableConfig, $source, $offset, $chunkSize)
                );

                if ($chunk === []) {
                    break;
                }

                $transformed = $this->transformChunk($chunk, $tableConfig, $engine, $keyRemapping, $keyRemappingConfig);

                try {
                    DB::connection($targetConn)->table($tableConfig->tableName)->insert($transformed);
                    $rows += count($transformed);
                } catch (Throwable $bulkError) {
                    $firstInsertError ??= $bulkError->getMessage();

                    foreach ($transformed as $rowIndexInChunk => $row) {
                        try {
                            DB::connection($targetConn)->table($tableConfig->tableName)->insert($row);
                            $rows++;
                        } catch (Throwable $rowError) {
                            $skipped++;

                            $sourceRow = (array) $chunk[$rowIndexInChunk];
                            $pkSnapshot = $this->extractPkSnapshot($sourceRow, $pkColumns);
                            $skippedRow = new SkippedRow(
                                tableName: $tableConfig->tableName,
                                chunkOffset: $offset,
                                rowIndex: $rowIndexInChunk,
                                pkSnapshot: $pkSnapshot,
                                sqlError: $rowError->getMessage(),
                            );
                            $skippedRows[] = $skippedRow;
                            $log->warning('row_skipped', [
                                'table' => $skippedRow->tableName,
                                'chunk_offset' => $skippedRow->chunkOffset,
                                'row_index' => $skippedRow->rowIndex,
                                'pk' => $skippedRow->pkSnapshot,
                                'error' => $skippedRow->sqlError,
                            ]);
                        }
                    }
                }

                $offset += count($chunk);
            } while (count($chunk) === $chunkSize);

            if ($rows === 0 && $skipped > 0) {
                $reason = sprintf('All %d rows failed to insert', $skipped);
                if ($firstInsertError !== null) {
                    $reason .= sprintf(': %s', $firstInsertError);
                }

                return [0, $skipped, true, $reason, $skippedRows];
            }

            return [$rows, $skipped, false, null, $skippedRows];
        } catch (Throwable $throwable) {
            return [$rows, $skipped, true, $throwable->getMessage(), $skippedRows];
        } finally {
            if ($options->disableForeignKeyChecks) {
                $this->enableFkChecks($targetConn, $target);
            }

            DB::purge($sourceConn);
            DB::purge($targetConn);
        }
    }

    private function transformChunk(
        array $chunk,
        TableCloningConfigData $tableConfig,
        AnonymizationEngine $engine,
        ?KeyRemappingService $keyRemapping,
        ?KeyRemappingConfigData $keyRemappingConfig,
    ): array {

        $transformed = [];

        foreach ($chunk as $row) {
            $rowArray = (array) $row;
            $transformedRow = [];

            foreach ($rowArray as $col => $val) {
                if (! is_string($col)) {
                    continue;
                }

                $colConfig = $tableConfig->getColumn($col);
                $transformedRow[$col] = $colConfig instanceof ColumnCloningConfigData ? $engine->transform($val, $colConfig) : $val;
            }

            if ($keyRemapping instanceof KeyRemappingService && $keyRemappingConfig instanceof KeyRemappingConfigData) {
                $transformedRow = $keyRemapping->applyToRow($transformedRow, $tableConfig->tableName, $keyRemappingConfig);
            }

            $transformed[] = $transformedRow;
        }

        return $transformed;
    }

    private function extractPkSnapshot(array $sourceRow, array $pkColumns): ?array
    {
        if ($pkColumns === []) {
            return null;
        }

        $snapshot = [];
        foreach ($pkColumns as $col) {
            if (array_key_exists($col, $sourceRow)) {
                $snapshot[$col] = $sourceRow[$col];
            }
        }

        return $snapshot === [] ? null : $snapshot;
    }

    private function buildChunkQuery(TableCloningConfigData $config, ConnectionData $source, int $offset, int $limit): string
    {
        $table = $config->tableName;
        $rows = $config->rows;
        $driver = $source->type->value;

        $quotedTable = $this->quoteTable($table, $source->type);

        if ($rows->strategy === 'full') {
            return match ($driver) {
                'pgsql', 'sqlite' => sprintf('SELECT * FROM "%s" LIMIT %d OFFSET %d', $table, $limit, $offset),
                default => sprintf('SELECT * FROM `%s` LIMIT %d OFFSET %d', $table, $limit, $offset),
            };
        }

        $totalLimit = $rows->limit ?? PHP_INT_MAX;
        $actualLimit = min($limit, max(0, $totalLimit - $offset));

        if ($actualLimit <= 0) {
            return sprintf('SELECT * FROM %s WHERE 1=0', $quotedTable);
        }

        $sortCol = $rows->sortBy ?? 'id';
        $direction = $rows->strategy === 'last' ? 'DESC' : 'ASC';

        return match ($driver) {
            'pgsql', 'sqlite' => sprintf('SELECT * FROM "%s" ORDER BY "%s" %s LIMIT %d OFFSET %d', $table, $sortCol, $direction, $actualLimit, $offset),
            default => sprintf('SELECT * FROM `%s` ORDER BY `%s` %s LIMIT %d OFFSET %d', $table, $sortCol, $direction, $actualLimit, $offset),
        };
    }

    private function quoteTable(string $name, DatabaseConnectionType $driver): string
    {
        return match ($driver) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => '`'.$name.'`',
            default => '"'.$name.'"',
        };
    }

    private function clearTable(string $connName, string $tableName, ClearMode $method, ConnectionData $connection): void
    {
        if ($method === ClearMode::Truncate) {

            if ($connection->type->value === 'sqlite') {
                DB::connection($connName)->table($tableName)->delete();
            } else {
                $quotedTable = $this->quoteTable($tableName, $connection->type);
                DB::connection($connName)->statement(sprintf('TRUNCATE TABLE %s', $quotedTable));
            }
        } elseif ($method === ClearMode::Delete) {
            DB::connection($connName)->table($tableName)->delete();
        }
    }

    private function disableFkChecks(string $connName, ConnectionData $connection): void
    {
        try {
            match ($connection->type->value) {
                'mysql', 'mariadb' => DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS=0'),
                'pgsql' => DB::connection($connName)->statement('SET session_replication_role = replica'),
                default => null,
            };
        } catch (Throwable) {
        }
    }

    private function enableFkChecks(string $connName, ConnectionData $connection): void
    {
        try {
            match ($connection->type->value) {
                'mysql', 'mariadb' => DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS=1'),
                'pgsql' => DB::connection($connName)->statement('SET session_replication_role = DEFAULT'),
                default => null,
            };
        } catch (Throwable) {
        }
    }
}
