<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Schema;

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\ForeignKeyData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Database\DatabaseConnectionService;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class SchemaInspector
{
    public function __construct(private readonly DatabaseConnectionService $connector) {}

    public function inspect(ConnectionData $connection): DatabaseSchemaData
    {
        $connName = $this->connector->open($connection);

        try {
            return $this->doInspect($connName, $connection);
        } finally {
            DB::purge($connName);
        }
    }

    /**
     * Inspecte une connexion Laravel déjà ouverte, par exemple celle d'un driver d'opendb (tunnel SSH compris).
     * Seuls le type et le nom de base de `$connection` sont utilisés.
     */
    public function inspectOpenConnection(string $connName, ConnectionData $connection): DatabaseSchemaData
    {
        return $this->doInspect($connName, $connection);
    }

    private function doInspect(string $connName, ConnectionData $connection): DatabaseSchemaData
    {
        return match ($connection->type) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => $this->inspectMysql($connName, $connection),
            DatabaseConnectionType::PostgreSQL => $this->inspectPgsql($connName, $connection),
            DatabaseConnectionType::Sqlite => $this->inspectSqlite($connName, $connection),
            DatabaseConnectionType::Dump => throw new RuntimeException('Dump connections have no schema to inspect.'),
        };
    }

    private function inspectMysql(string $connName, ConnectionData $connection): DatabaseSchemaData
    {
        $dbName = $connection->database ?? '';

        $tableRows = DB::connection($connName)->select(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME"
        );

        $tables = [];

        foreach ($tableRows as $tableRow) {

            $tableName = $tableRow->TABLE_NAME;

            $columnRows = DB::connection($connName)->select(
                'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [$tableName]
            );

            $columns = [];

            foreach ($columnRows as $col) {

                $colName = $col->COLUMN_NAME;

                $dataType = $col->DATA_TYPE;

                $columnType = $col->COLUMN_TYPE;

                $isNullable = $col->IS_NULLABLE;
                $rawDefault = $col->COLUMN_DEFAULT ?? null;
                $colDefault = is_scalar($rawDefault) ? (string) $rawDefault : null;

                $colKey = $col->COLUMN_KEY;

                $mods = ColumnTypeParser::parse($columnType);

                $columns[] = new ColumnSchemaData(
                    name: $colName,
                    type: $dataType,
                    nullable: $isNullable === 'YES',
                    default: $colDefault,
                    isPrimary: $colKey === 'PRI',
                    unsigned: str_contains(strtolower($columnType), 'unsigned'),
                    length: $mods['length'],
                    precision: $mods['precision'],
                    scale: $mods['scale'],
                );
            }

            $fkRows = DB::connection($connName)->select(
                'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$tableName]
            );

            $foreignKeys = [];

            foreach ($fkRows as $fk) {

                $fkColName = $fk->COLUMN_NAME;

                $refTable = $fk->REFERENCED_TABLE_NAME;

                $refCol = $fk->REFERENCED_COLUMN_NAME;

                $foreignKeys[] = new ForeignKeyData(
                    columnName: $fkColName,
                    referencedTable: $refTable,
                    referencedColumn: $refCol,
                );
            }

            $tables[] = new TableSchemaData(
                name: $tableName,
                columns: $columns,
                foreignKeys: $foreignKeys,
            );
        }

        return new DatabaseSchemaData(databaseName: $dbName, tables: $tables);
    }

    private function inspectPgsql(string $connName, ConnectionData $connection): DatabaseSchemaData
    {
        $dbName = $connection->database ?? '';

        $tableRows = DB::connection($connName)->select(
            'SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename'
        );

        $tables = [];

        foreach ($tableRows as $tableRow) {

            $tableName = $tableRow->tablename;

            $columnRows = DB::connection($connName)->select(
                "SELECT column_name, data_type, is_nullable, column_default,
                    character_maximum_length, numeric_precision, numeric_scale,
                    (SELECT COUNT(*) FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
                     WHERE tc.table_name = c.table_name AND kcu.column_name = c.column_name
                     AND tc.constraint_type = 'PRIMARY KEY' AND tc.table_schema = current_schema()) > 0 as is_primary
                FROM information_schema.columns c
                WHERE table_schema = current_schema() AND table_name = ?
                ORDER BY ordinal_position",
                [$tableName]
            );

            $columns = [];

            foreach ($columnRows as $col) {

                $colName = $col->column_name;

                $dataType = $col->data_type;

                $isNullable = $col->is_nullable;
                $rawColDefault = $col->column_default ?? null;
                $colDefault = is_scalar($rawColDefault) ? (string) $rawColDefault : null;
                $isPrimary = (bool) $col->is_primary;

                $columns[] = new ColumnSchemaData(
                    name: $colName,
                    type: $dataType,
                    nullable: $isNullable === 'YES',
                    default: $colDefault,
                    isPrimary: $isPrimary,
                    length: $this->intOrNull($col->character_maximum_length ?? null),
                    precision: $this->intOrNull($col->numeric_precision ?? null),
                    scale: $this->intOrNull($col->numeric_scale ?? null),
                );
            }

            $fkRows = DB::connection($connName)->select(
                "SELECT kcu.column_name, ccu.table_name AS referenced_table, ccu.column_name AS referenced_column
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
                JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = ?
                AND tc.table_schema = current_schema()",
                [$tableName]
            );

            $foreignKeys = [];

            foreach ($fkRows as $fk) {

                $fkColName = $fk->column_name;

                $refTable = $fk->referenced_table;

                $refCol = $fk->referenced_column;

                $foreignKeys[] = new ForeignKeyData(
                    columnName: $fkColName,
                    referencedTable: $refTable,
                    referencedColumn: $refCol,
                );
            }

            $tables[] = new TableSchemaData(
                name: $tableName,
                columns: $columns,
                foreignKeys: $foreignKeys,
            );
        }

        return new DatabaseSchemaData(databaseName: $dbName, tables: $tables);
    }

    private function inspectSqlite(string $connName, ConnectionData $connection): DatabaseSchemaData
    {
        $dbName = $connection->database ?? 'sqlite';

        $pdo = DB::connection($connName)->getPdo();

        $tableRows = DB::connection($connName)->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        $tables = [];

        foreach ($tableRows as $tableRow) {

            $tableName = $tableRow->name;

            $colRows = $pdo->query('PRAGMA table_info('.$tableName.')');

            if ($colRows === false) {
                throw new RuntimeException(sprintf('Failed to get column info for table %s', $tableName));
            }

            $colData = $colRows->fetchAll(PDO::FETCH_OBJ);

            $columns = [];

            foreach ($colData as $col) {

                $colName = $col->name;

                $colType = $col->type;
                $notNull = (bool) $col->notnull;
                $rawDfltValue = $col->dflt_value ?? null;
                $dfltValue = is_scalar($rawDfltValue) ? (string) $rawDfltValue : null;
                $pk = (bool) $col->pk;

                $mods = ColumnTypeParser::parse($colType);

                $columns[] = new ColumnSchemaData(
                    name: $colName,
                    type: strtolower($colType),
                    nullable: ! $notNull,
                    default: $dfltValue,
                    isPrimary: $pk,
                    length: $mods['length'],
                    precision: $mods['precision'],
                    scale: $mods['scale'],
                );
            }

            $fkRows = $pdo->query('PRAGMA foreign_key_list('.$tableName.')');

            if ($fkRows === false) {
                throw new RuntimeException(sprintf('Failed to get foreign key info for table %s', $tableName));
            }

            $fkData = $fkRows->fetchAll(PDO::FETCH_OBJ);

            $foreignKeys = [];

            foreach ($fkData as $fk) {

                $fkFrom = $fk->from;

                $fkTable = $fk->table;

                $fkTo = $fk->to;

                $foreignKeys[] = new ForeignKeyData(
                    columnName: $fkFrom,
                    referencedTable: $fkTable,
                    referencedColumn: $fkTo,
                );
            }

            $tables[] = new TableSchemaData(
                name: $tableName,
                columns: $columns,
                foreignKeys: $foreignKeys,
            );
        }

        return new DatabaseSchemaData(databaseName: $dbName, tables: $tables);
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
