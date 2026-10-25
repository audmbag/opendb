<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Database\DatabaseConnectionService;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use App\Services\Cloning\Engine\Schema\SchemaInspector;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

class SchemaReplicator
{
    public function __construct(
        private readonly SchemaInspector $inspector,
        private readonly DatabaseConnectionService $connector,
    ) {}

    public function replicate(
        ConnectionData $source,
        ConnectionData $target,
        DatabaseSchemaData $sourceSchema,
        array $tables,
        bool $enforceColumnTypes,
        bool $dropUnknownTables,
        bool $dropExtraColumns = false,
    ): array {
        $targetConnName = $this->connector->open($target);
        $sameDbType = $source->type === $target->type;

        $failedTables = [];

        try {
            $targetSchema = $this->inspector->inspect($target);

            foreach ($tables as $tableName) {
                $sourceTable = $sourceSchema->getTable($tableName);

                if (! $sourceTable instanceof TableSchemaData) {
                    continue;
                }

                $targetTable = $targetSchema->getTable($tableName);

                if (! $targetTable instanceof TableSchemaData) {
                    $created = false;

                    if ($sameDbType) {
                        $nativeSql = $this->fetchNativeCreateTableDdl($source, $tableName);

                        if ($nativeSql !== null) {
                            try {
                                DB::connection($targetConnName)->statement($nativeSql);
                                $created = true;
                            } catch (Throwable) {

                            }
                        }
                    }

                    if (! $created) {
                        try {
                            $fallbackSql = $this->buildCreateTableSql($tableName, $sourceTable->columns, $target->type);
                            DB::connection($targetConnName)->statement($fallbackSql);
                        } catch (Throwable $e) {
                            $failedTables[$tableName] = $e->getMessage();
                        }
                    }
                } else {
                    $sourceColNames = array_map(static fn (ColumnSchemaData $c): string => $c->name, $sourceTable->columns);
                    $targetColNames = array_map(static fn (ColumnSchemaData $c): string => $c->name, $targetTable->columns);

                    if ($enforceColumnTypes) {

                        foreach ($sourceTable->columns as $col) {
                            if (! in_array($col->name, $targetColNames, true)) {
                                $alterSql = $this->buildAddColumnSql($tableName, $col, $target->type);
                                DB::connection($targetConnName)->statement($alterSql);
                            }
                        }
                    }

                    if ($dropExtraColumns) {

                        foreach ($targetTable->columns as $col) {
                            if (! in_array($col->name, $sourceColNames, true)) {
                                $dropColSql = $this->buildDropColumnSql($tableName, $col->name, $target->type);
                                DB::connection($targetConnName)->statement($dropColSql);
                            }
                        }
                    }
                }
            }

            if ($dropUnknownTables) {
                $sourceTableNames = array_map(static fn (TableSchemaData $t): string => $t->name, $sourceSchema->tables);

                foreach ($targetSchema->tables as $targetTable) {
                    if (! in_array($targetTable->name, $sourceTableNames, true)) {
                        $dropSql = $this->buildDropTableSql($targetTable->name, $target->type);
                        DB::connection($targetConnName)->statement($dropSql);
                    }
                }
            }
        } finally {
            DB::purge($targetConnName);
        }

        return $failedTables;
    }

    private function buildCreateTableSql(string $tableName, array $columns, DatabaseConnectionType $driver): string
    {
        $colDefs = [];
        $pkCols = [];

        foreach ($columns as $col) {
            $mappedType = $this->mapType($col->type, $driver);
            $nullable = $col->nullable ? 'NULL' : 'NOT NULL';
            $quotedName = $this->quoteIdentifier($col->name, $driver);
            $colDefs[] = sprintf('  %s %s %s', $quotedName, $mappedType, $nullable);

            if ($col->isPrimary) {
                $pkCols[] = $this->quoteIdentifier($col->name, $driver);
            }
        }

        if ($pkCols !== []) {
            $colDefs[] = '  PRIMARY KEY ('.implode(', ', $pkCols).')';
        }

        $colsSql = implode(",\n", $colDefs);
        $quotedTable = $this->quoteIdentifier($tableName, $driver);

        return "CREATE TABLE IF NOT EXISTS {$quotedTable} (\n{$colsSql}\n)";
    }

    private function buildAddColumnSql(string $tableName, ColumnSchemaData $col, DatabaseConnectionType $driver): string
    {
        $mappedType = $this->mapType($col->type, $driver);
        $nullable = $col->nullable ? 'NULL' : 'NOT NULL';
        $quotedTable = $this->quoteIdentifier($tableName, $driver);
        $quotedCol = $this->quoteIdentifier($col->name, $driver);

        return sprintf('ALTER TABLE %s ADD COLUMN %s %s %s', $quotedTable, $quotedCol, $mappedType, $nullable);
    }

    private function buildDropColumnSql(string $tableName, string $columnName, DatabaseConnectionType $driver): string
    {
        $quotedTable = $this->quoteIdentifier($tableName, $driver);
        $quotedCol = $this->quoteIdentifier($columnName, $driver);

        return sprintf('ALTER TABLE %s DROP COLUMN %s', $quotedTable, $quotedCol);
    }

    private function buildDropTableSql(string $tableName, DatabaseConnectionType $driver): string
    {
        $quotedTable = $this->quoteIdentifier($tableName, $driver);

        return 'DROP TABLE IF EXISTS '.$quotedTable;
    }

    private function mapType(string $sourceType, DatabaseConnectionType $driver): string
    {
        $type = strtolower(trim($sourceType));

        return match (true) {

            in_array($type, ['varchar', 'character varying', 'nvarchar'], true) => match ($driver) {
                default => 'VARCHAR(255)',
            },
            in_array($type, ['char', 'character'], true) => match ($driver) {
                default => 'CHAR(1)',
            },

            in_array($type, ['tinyint(1)', 'boolean', 'bool'], true) => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'TINYINT(1)',
                default => 'BOOLEAN',
            },
            in_array($type, ['tinyint', 'int1'], true) => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'TINYINT',
                default => 'SMALLINT',
            },
            in_array($type, ['smallint', 'int2'], true) => 'SMALLINT',
            in_array($type, ['mediumint', 'int3'], true) => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'MEDIUMINT',
                default => 'INT',
            },
            in_array($type, ['int', 'integer', 'int4'], true) => 'INT',
            in_array($type, ['bigint', 'int8'], true) => 'BIGINT',
            in_array($type, ['serial', 'serial4'], true) => match ($driver) {
                DatabaseConnectionType::PostgreSQL => 'SERIAL',
                default => 'INT',
            },
            $type === 'bigserial' => match ($driver) {
                DatabaseConnectionType::PostgreSQL => 'BIGSERIAL',
                default => 'BIGINT',
            },

            in_array($type, ['float', 'float4', 'real'], true) => 'FLOAT',
            in_array($type, ['double', 'double precision', 'float8'], true) => 'DOUBLE',
            in_array($type, ['decimal', 'numeric'], true) => 'DECIMAL(10,2)',

            in_array($type, ['text', 'clob'], true) => 'TEXT',
            $type === 'tinytext' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'TINYTEXT',
                default => 'TEXT',
            },
            $type === 'mediumtext' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'MEDIUMTEXT',
                default => 'TEXT',
            },
            $type === 'longtext' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'LONGTEXT',
                default => 'TEXT',
            },

            in_array($type, ['blob', 'binary'], true) => match ($driver) {
                default => 'BLOB',
            },
            $type === 'tinyblob' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'TINYBLOB',
                default => 'BLOB',
            },
            $type === 'mediumblob' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'MEDIUMBLOB',
                default => 'BLOB',
            },
            $type === 'longblob' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'LONGBLOB',
                default => 'BLOB',
            },
            $type === 'varbinary' => match ($driver) {
                default => 'BLOB',
            },

            $type === 'enum' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'VARCHAR(255)',
                default => 'VARCHAR(255)',
            },
            $type === 'set' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'VARCHAR(255)',
                default => 'TEXT',
            },

            $type === 'date' => 'DATE',
            in_array($type, ['time', 'time without time zone'], true) => 'TIME',
            in_array($type, ['datetime', 'timestamp', 'timestamp without time zone', 'timestamp with time zone'], true) => match ($driver) {
                DatabaseConnectionType::PostgreSQL => 'TIMESTAMP',
                default => 'DATETIME',
            },
            $type === 'year' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'YEAR',
                default => 'SMALLINT',
            },

            in_array($type, ['json', 'jsonb'], true) => match ($driver) {
                DatabaseConnectionType::Sqlite => 'TEXT',
                default => 'JSON',
            },

            $type === 'uuid' => match ($driver) {
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => 'CHAR(36)',
                default => 'UUID',
            },
            default => 'TEXT',
        };
    }

    public function correctAutoIncrement(ConnectionData $target, string $tableName, string $pkColumn): void
    {
        if (! in_array($target->type, [DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB], true)) {
            return;
        }

        $connName = $this->connector->open($target);

        try {
            $quotedTable = '`'.$tableName.'`';
            $quotedCol = '`'.$pkColumn.'`';

            $rows = DB::connection($connName)->select(
                'SELECT COALESCE(MAX('.$quotedCol.'), 0) + 1 AS next_val FROM '.$quotedTable
            );

            $row = (array) ($rows[0] ?? new stdClass);
            $rawVal = $row['next_val'] ?? 1;
            $nextVal = max(1, is_numeric($rawVal) ? (int) $rawVal : 1);

            DB::connection($connName)->statement(
                'ALTER TABLE '.$quotedTable.' AUTO_INCREMENT = '.$nextVal
            );
        } finally {
            DB::purge($connName);
        }
    }

    private function fetchNativeCreateTableDdl(ConnectionData $source, string $tableName): ?string
    {
        if (! in_array($source->type, [DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB], true)) {
            return null;
        }

        try {
            $sourceConnName = $this->connector->open($source);

            try {
                $quotedTable = '`'.$tableName.'`';

                $rows = DB::connection($sourceConnName)->select('SHOW CREATE TABLE '.$quotedTable);

                if ($rows === []) {
                    return null;
                }

                $row = (array) $rows[0];
                $ddl = $row['Create Table'] ?? null;

                if (! is_string($ddl)) {
                    return null;
                }

                return $this->sanitiseNativeDdl($ddl);
            } finally {
                DB::purge($sourceConnName);
            }
        } catch (Throwable) {
            return null;
        }
    }

    private function sanitiseNativeDdl(string $ddl): string
    {

        $ddl = preg_replace('/,?\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+(?:DELETE|UPDATE)\s+\w+(?:\s+(?!ON\b)\w+)?)*/i', '', $ddl) ?? $ddl;

        $ddl = preg_replace('/,(\s*\))/m', '$1', $ddl) ?? $ddl;

        $ddl = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $ddl) ?? $ddl;

        $ddl = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $ddl) ?? $ddl;

        return $ddl;
    }

    private function quoteIdentifier(string $name, DatabaseConnectionType $driver): string
    {
        return match ($driver) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => '`'.$name.'`',
            default => '"'.$name.'"',
        };
    }
}
