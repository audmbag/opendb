<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Audit\CloningAuditLog;
use App\Services\Cloning\Engine\Cloning\Transfer\RowTransformer;
use App\Services\Cloning\Engine\Cloning\Transfer\SameServerCopier;
use App\Services\Cloning\Engine\Cloning\Transfer\TableTransfer;
use App\Services\Cloning\Engine\Data\Cloning\CloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\CloningOptionsData;
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
use App\Services\Cloning\Engine\Exceptions\CloningCancelledException;
use Illuminate\Support\Facades\DB;
use Throwable;

class CloningRunOrchestrator
{
    public function __construct(
        private readonly DatabaseConnectionService $connector,
        private readonly SchemaReplicator $replicator,
        private readonly DependencyResolver $resolver,
    ) {}

    /**
     * @throws CloningCancelledException Si `$monitor` demande l'arrêt : entre deux tables ou deux lots de lignes
     */
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
        ?CloningMonitor $monitor = null,
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
        $createdTables = [];
        $onSchemaWarning = fn (string $event, array $context) => $log->warning($event, $context);

        if (! $skipSchema) {
            $schemaFailures = $this->replicator->replicate(
                $source,
                $target,
                $sourceSchema,
                $sortedTables,
                $config->options->enforceColumnTypes,
                $config->options->dropUnknownTables,
                $config->options->dropExtraColumns,
                $onSchemaWarning,
                $createdTables,
            );

            if ($schemaFailures === []) {
                $log->info('schema_replicated', ['tables' => $sortedTables]);
            } else {
                foreach ($schemaFailures as $failedTable => $errorMsg) {
                    $log->error('schema_table_failed', ['table' => $failedTable, 'error' => $errorMsg]);
                }
            }
        }

        $this->stopIfCancelled($monitor);

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

        // Une connexion de chaque côté pour tout le clonage, plutôt que deux nouvelles par table.
        $sourceConn = $this->connector->open($source);
        $targetConn = $this->connector->open($target);

        try {
            if ($config->options->disableForeignKeyChecks) {
                $this->disableFkChecks($targetConn, $target);
            }

            $this->writeWithoutWaitingForDisk($targetConn, $target);

            // Les dates avec fuseau de PostgreSQL sont lues en UTC, que ValueAdapter écrit sans fuseau ailleurs.
            if ($source->type === DatabaseConnectionType::PostgreSQL) {
                DB::connection($sourceConn)->statement("SET TIME ZONE 'UTC'");
            }
            $copier = SameServerCopier::between($source, $target, DB::connection($targetConn)->getPdo(), $this->connector);

            foreach (array_values($sortedTables) as $index => $tableName) {
                $this->stopIfCancelled($monitor);

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
                    $sourceTable,
                    $sourceConn,
                    $source,
                    $targetConn,
                    $target,
                    $pkColumns,
                    $engine,
                    $keyRemapping,
                    $config->keyRemapping,
                    $copier,
                    $monitor,
                    $index + 1,
                    count($sortedTables),
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
                            $this->replicator->correctAutoIncrement($target, $tableName, $pkColumn, $targetConn);
                        } catch (Throwable $e) {
                            $log->warning('auto_increment_correction_failed', ['table' => $tableName, 'error' => $e->getMessage()]);
                        }
                    }
                }
            }

            // Les clés étrangères viennent après les lignes : la copie n'a pas à suivre l'ordre de leurs dépendances.
            $this->replicator->addForeignKeys($source, $target, $sourceSchema, $createdTables, $targetConn, $onSchemaWarning);
        } finally {
            if ($config->options->disableForeignKeyChecks) {
                $this->enableFkChecks($targetConn, $target);
            }

            DB::purge($sourceConn);
            DB::purge($targetConn);
        }

        $duration = microtime(true) - $start;

        return new RunResultData(
            success: $success,
            tables: $tableResults,
            totalRows: $totalRows,
            skippedRows: $totalSkipped,
            durationSeconds: $duration,
            failureReason: $success ? null : __('cloning.errors.tables_failed'),
        );
    }

    /**
     * Colonne dont la cible génère les valeurs et qu'il faut faire repartir après la copie : la clé primaire entière
     * d'une table MySQL ou MariaDB, la clé auto-incrémentée d'une table PostgreSQL.
     */
    private function findIntegerPkColumn(ConnectionData $target, TableSchemaData $table): ?string
    {
        $pkColumns = array_values(array_filter($table->columns, static fn (ColumnSchemaData $c): bool => $c->isPrimary));

        if (count($pkColumns) !== 1) {
            return null;
        }

        return match ($target->type) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => in_array(strtolower($pkColumns[0]->type), ['int', 'bigint', 'mediumint', 'smallint', 'tinyint', 'integer'], true) ? $pkColumns[0]->name : null,
            DatabaseConnectionType::PostgreSQL => $pkColumns[0]->autoIncrement ? $pkColumns[0]->name : null,
            default => null,
        };
    }

    /**
     * @param  list<string>  $pkColumns
     * @return array{int, int, bool, ?string, list<SkippedRow>}
     *
     * @throws CloningCancelledException
     */
    private function transferTable(
        CloningAuditLog $log,
        CloningOptionsData $options,
        TableCloningConfigData $tableConfig,
        TableSchemaData $sourceTable,
        string $sourceConn,
        ConnectionData $source,
        string $targetConn,
        ConnectionData $target,
        array $pkColumns,
        AnonymizationEngine $engine,
        ?KeyRemappingService $keyRemapping,
        ?KeyRemappingConfigData $keyRemappingConfig,
        ?SameServerCopier $copier,
        ?CloningMonitor $monitor,
        int $position,
        int $tableCount,
    ): array {
        try {
            if ($tableConfig->rows->clear !== ClearMode::None) {
                $this->clearTable($targetConn, $tableConfig->tableName, $tableConfig->rows->clear, $target);
            }

            $expectedRows = $this->expectedRows($sourceConn, $tableConfig);
            $monitor?->tableStarted($tableConfig->tableName, $position, $tableCount, $expectedRows);

            return new TableTransfer(
                log: $log,
                config: $tableConfig,
                schema: $sourceTable,
                source: DB::connection($sourceConn)->getPdo(),
                sourceType: $source->type,
                target: DB::connection($targetConn)->getPdo(),
                targetType: $target->type,
                transformer: RowTransformer::for($tableConfig, $engine, $keyRemapping, $keyRemappingConfig),
                copier: $copier,
                chunkSize: $options->chunkSize,
                expectedRows: $expectedRows,
                primaryKey: $pkColumns,
                afterChunk: function (int $rows) use ($monitor): void {
                    $monitor?->rowsCopied($rows);
                    $this->stopIfCancelled($monitor);
                },
            )->run();
        } catch (CloningCancelledException $cancelled) {
            throw $cancelled;
        } catch (Throwable $throwable) {
            return [0, 0, true, $throwable->getMessage(), []];
        }
    }

    /**
     * @throws CloningCancelledException
     */
    private function stopIfCancelled(?CloningMonitor $monitor): void
    {
        if ($monitor?->isCancelled() === true) {
            throw new CloningCancelledException;
        }
    }

    /**
     * Lignes que la table va copier, pour suivre sa progression ; null si la source refuse de les compter.
     */
    private function expectedRows(string $sourceConn, TableCloningConfigData $config): ?int
    {
        try {
            $count = DB::connection($sourceConn)->table($config->tableName)->count();
        } catch (Throwable) {
            return null;
        }

        return $config->rows->strategy === 'full' || $config->rows->limit === null ? $count : min($count, $config->rows->limit);
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

    /**
     * La cible n'attend plus que chaque validation soit écrite sur le disque, pour cette seule connexion : sur un disque
     * ordinaire, ces attentes prennent plus de la moitié du clonage d'une base SQLite. Sous SQLite, une coupure de courant
     * ou un plantage du système pendant le clonage peut alors abîmer le fichier cible, pas un arrêt de PHP ; sous
     * PostgreSQL, elle ne perd que les dernières validations. MySQL n'offre pas ce réglage à une session.
     */
    private function writeWithoutWaitingForDisk(string $connName, ConnectionData $connection): void
    {
        try {
            match ($connection->type) {
                DatabaseConnectionType::Sqlite => DB::connection($connName)->statement('PRAGMA synchronous = OFF'),
                DatabaseConnectionType::PostgreSQL => DB::connection($connName)->statement('SET synchronous_commit = off'),
                default => null,
            };
        } catch (Throwable) {
        }
    }

    private function disableFkChecks(string $connName, ConnectionData $connection): void
    {
        try {
            match ($connection->type->value) {
                'mysql', 'mariadb' => DB::connection($connName)->statement('SET FOREIGN_KEY_CHECKS=0'),
                'pgsql' => DB::connection($connName)->statement('SET session_replication_role = replica'),
                'sqlite' => DB::connection($connName)->statement('PRAGMA foreign_keys = OFF'),
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
