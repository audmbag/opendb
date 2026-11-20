<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Schema;

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnDefaultData;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\ForeignKeyConstraintData;
use App\Services\Cloning\Engine\Data\Schema\ForeignKeyData;
use App\Services\Cloning\Engine\Data\Schema\IndexSchemaData;
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
                'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
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
                    declaredType: $columnType,
                    autoIncrement: str_contains(strtolower((string) $col->EXTRA), 'auto_increment'),
                    defaultValue: ColumnDefaultData::parse(
                        is_scalar($rawDefault) ? (string) $rawDefault : null,
                        mysqlExpression: str_contains(strtolower((string) $col->EXTRA), 'default_generated'),
                        quotedLiterals: $connection->type === DatabaseConnectionType::MariaDB,
                        backslashEscapes: $connection->type === DatabaseConnectionType::MariaDB,
                    ),
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

            $indexRows = DB::connection($connName)->select(
                'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
                [$tableName]
            );

            [$indexes, $primaryKey] = $this->groupIndexes(array_map(
                fn (object $row): array => [(string) $row->INDEX_NAME, ! $row->NON_UNIQUE, $row->COLUMN_NAME, $row->INDEX_NAME === 'PRIMARY'],
                $indexRows,
            ));

            $constraintRows = DB::connection($connName)->select(
                'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE k
                JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
                WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
                [$tableName]
            );

            $tables[] = new TableSchemaData(
                name: $tableName,
                columns: $columns,
                foreignKeys: $foreignKeys,
                indexes: $indexes,
                primaryKey: $primaryKey,
                constraints: $this->groupConstraints(array_map(
                    fn (object $row): array => [$row->CONSTRAINT_NAME, $row->COLUMN_NAME, $row->REFERENCED_TABLE_NAME, $row->REFERENCED_COLUMN_NAME, $row->DELETE_RULE, $row->UPDATE_RULE],
                    $constraintRows,
                )),
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
                "SELECT column_name, data_type, is_nullable, column_default, is_identity,
                    character_maximum_length, numeric_precision, numeric_scale,
                    (SELECT format_type(a.atttypid, a.atttypmod) FROM pg_attribute a
                     WHERE a.attrelid = to_regclass(quote_ident(c.table_name)) AND a.attname = c.column_name) AS declared_type,
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
                    declaredType: is_string($col->declared_type) ? $col->declared_type : null,
                    autoIncrement: $col->is_identity === 'YES' || str_starts_with((string) $colDefault, 'nextval('),
                    defaultValue: ColumnDefaultData::parse($colDefault),
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

            // Une colonne d'index à 0 est une expression ; un index partiel (indpred) n'est pas repris.
            $indexRows = DB::connection($connName)->select(
                'SELECT ic.relname AS index_name, i.indisunique, i.indisprimary, a.attname
                FROM pg_index i
                JOIN pg_class ic ON ic.oid = i.indexrelid
                CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, position)
                LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum
                WHERE i.indrelid = to_regclass(quote_ident(CAST(? AS text))) AND i.indpred IS NULL
                ORDER BY ic.relname, k.position',
                [$tableName]
            );

            [$indexes, $primaryKey] = $this->groupIndexes(array_map(
                fn (object $row): array => [(string) $row->index_name, (bool) $row->indisunique, $row->attname, (bool) $row->indisprimary],
                $indexRows,
            ));

            $constraintRows = DB::connection($connName)->select(
                'SELECT c.conname, a.attname AS column_name, rt.relname AS referenced_table, ra.attname AS referenced_column, c.confdeltype, c.confupdtype
                FROM pg_constraint c
                CROSS JOIN LATERAL unnest(c.conkey, c.confkey) WITH ORDINALITY AS k(col, refcol, position)
                JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.col
                JOIN pg_class rt ON rt.oid = c.confrelid
                JOIN pg_attribute ra ON ra.attrelid = c.confrelid AND ra.attnum = k.refcol
                WHERE c.contype = \'f\' AND c.conrelid = to_regclass(quote_ident(CAST(? AS text)))
                ORDER BY c.conname, k.position',
                [$tableName]
            );

            $tables[] = new TableSchemaData(
                name: $tableName,
                columns: $columns,
                foreignKeys: $foreignKeys,
                indexes: $indexes,
                primaryKey: $primaryKey,
                constraints: $this->groupConstraints(array_map(
                    fn (object $row): array => [$row->conname, $row->column_name, $row->referenced_table, $row->referenced_column, $row->confdeltype, $row->confupdtype],
                    $constraintRows,
                )),
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

            $quotedTable = '"'.str_replace('"', '""', $tableName).'"';
            $colRows = $pdo->query('PRAGMA table_info('.$quotedTable.')');

            if ($colRows === false) {
                throw new RuntimeException(sprintf('Failed to get column info for table %s', $tableName));
            }

            $colData = $colRows->fetchAll(PDO::FETCH_OBJ);

            $columns = [];
            $primaryKey = [];

            foreach ($colData as $col) {

                $colName = $col->name;

                $colType = $col->type;
                $notNull = (bool) $col->notnull;
                $rawDfltValue = $col->dflt_value ?? null;
                $dfltValue = is_scalar($rawDfltValue) ? (string) $rawDfltValue : null;
                $pk = (bool) $col->pk;

                if ($pk) {
                    $primaryKey[(int) $col->pk] = $colName;
                }

                $mods = ColumnTypeParser::parse($colType);

                $columns[] = new ColumnSchemaData(
                    name: $colName,
                    // Sans ses paramètres, comme les autres bases : « varchar(255) » est un varchar de longueur 255.
                    type: strtolower(trim(preg_replace('/\s*\(.*$/', '', $colType) ?? $colType)),
                    nullable: ! $notNull,
                    default: $dfltValue,
                    isPrimary: $pk,
                    length: $mods['length'],
                    precision: $mods['precision'],
                    scale: $mods['scale'],
                    declaredType: $colType !== '' ? $colType : null,
                    defaultValue: ColumnDefaultData::parse($dfltValue),
                );
            }

            ksort($primaryKey);
            $primaryKey = array_values($primaryKey);
            $columns = $this->withSqliteRowidKey($columns, $primaryKey);

            $fkRows = $pdo->query('PRAGMA foreign_key_list('.$quotedTable.')');

            if ($fkRows === false) {
                throw new RuntimeException(sprintf('Failed to get foreign key info for table %s', $tableName));
            }

            $fkData = $fkRows->fetchAll(PDO::FETCH_OBJ);

            // « to » est nul quand la clé vise, sans la nommer, la clé primaire de l'autre table : elle est lue ici.
            $referencedKeys = [];
            foreach ($fkData as $fk) {
                if ($fk->to === null) {
                    $referencedKeys[$fk->table] ??= $this->sqlitePrimaryKey($pdo, (string) $fk->table);
                    $fk->to = $referencedKeys[$fk->table][$fk->seq] ?? null;
                }
            }

            // SQLite ne nomme pas ses clés étrangères.
            $constraints = array_map(
                fn (ForeignKeyConstraintData $constraint): ForeignKeyConstraintData => new ForeignKeyConstraintData(
                    null,
                    $constraint->columns,
                    $constraint->referencedTable,
                    in_array(null, $constraint->referencedColumns ?? [], true) ? null : $constraint->referencedColumns,
                    $constraint->onDelete,
                    $constraint->onUpdate,
                ),
                $this->groupConstraints(array_map(
                    fn (object $fk): array => ['fk'.$fk->id, $fk->from, $fk->table, $fk->to, $fk->on_delete, $fk->on_update],
                    $fkData,
                )),
            );

            $foreignKeys = [];

            foreach (array_filter($fkData, fn (object $fk): bool => $fk->to !== null) as $fk) {

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
                indexes: $this->sqliteIndexes($pdo, $quotedTable),
                primaryKey: $primaryKey,
                constraints: $constraints,
            );
        }

        return new DatabaseSchemaData(databaseName: $dbName, tables: $tables);
    }

    /**
     * Colonnes de la clé primaire d'une table SQLite, dans leur ordre ; vide pour une table absente.
     *
     * @return list<string>
     */
    private function sqlitePrimaryKey(PDO $pdo, string $table): array
    {
        $columns = array_filter($pdo->query('PRAGMA table_info("'.str_replace('"', '""', $table).'")')->fetchAll(PDO::FETCH_ASSOC), fn (array $column): bool => (int) $column['pk'] > 0);
        usort($columns, fn (array $a, array $b): int => $a['pk'] <=> $b['pk']);

        return array_values(array_map(fn (array $column): string => (string) $column['name'], $columns));
    }

    /**
     * Une clé primaire INTEGER sur une seule colonne est le rowid de SQLite : la base la génère.
     *
     * @param  list<ColumnSchemaData>  $columns
     * @param  list<string>  $primaryKey
     * @return list<ColumnSchemaData>
     */
    private function withSqliteRowidKey(array $columns, array $primaryKey): array
    {
        if (count($primaryKey) !== 1) {
            return $columns;
        }

        return array_map(
            fn (ColumnSchemaData $column): ColumnSchemaData => $column->name === $primaryKey[0] && $column->type === 'integer'
                ? $column->withAutoIncrement()
                : $column,
            $columns,
        );
    }

    /**
     * Index créés par CREATE INDEX ou par une contrainte UNIQUE, sans les index partiels ni ceux sur une expression.
     *
     * @return list<IndexSchemaData>
     */
    private function sqliteIndexes(PDO $pdo, string $quotedTable): array
    {
        $indexes = [];

        foreach ($pdo->query('PRAGMA index_list('.$quotedTable.')')->fetchAll(PDO::FETCH_ASSOC) as $index) {
            if ($index['origin'] === 'pk' || (int) $index['partial'] === 1) {
                continue;
            }

            $columns = array_column($pdo->query('PRAGMA index_info("'.str_replace('"', '""', $index['name']).'")')->fetchAll(PDO::FETCH_ASSOC), 'name');

            if ($columns !== [] && ! in_array(null, $columns, true)) {
                $indexes[] = new IndexSchemaData((string) $index['name'], array_map(strval(...), $columns), (bool) $index['unique']);
            }
        }

        return $indexes;
    }

    /**
     * Regroupe les lignes d'index (nom, unique, colonne, clé primaire) dans l'ordre de leurs colonnes. Un index qui
     * porte sur une expression (colonne nulle) est écarté.
     *
     * @param  list<array{string, bool, ?string, bool}>  $rows
     * @return array{list<IndexSchemaData>, list<string>} Les index, puis les colonnes de la clé primaire
     */
    private function groupIndexes(array $rows): array
    {
        $grouped = [];

        foreach ($rows as [$name, $unique, $column, $primary]) {
            $grouped[$name] ??= ['unique' => $unique, 'primary' => $primary, 'columns' => []];
            $grouped[$name]['columns'][] = $column;
        }

        $indexes = [];
        $primaryKey = [];

        foreach ($grouped as $name => $index) {
            if (in_array(null, $index['columns'], true)) {
                continue;
            }

            if ($index['primary']) {
                $primaryKey = $index['columns'];

                continue;
            }

            $indexes[] = new IndexSchemaData((string) $name, $index['columns'], $index['unique']);
        }

        return [$indexes, $primaryKey];
    }

    /**
     * Regroupe les lignes de clés étrangères (nom, colonne, table et colonne visées, règles de suppression et de mise à
     * jour) dans l'ordre de leurs colonnes.
     *
     * @param  list<array{string, string, string, ?string, ?string, ?string}>  $rows
     * @return list<ForeignKeyConstraintData>
     */
    private function groupConstraints(array $rows): array
    {
        $grouped = [];

        foreach ($rows as [$name, $column, $referencedTable, $referencedColumn, $onDelete, $onUpdate]) {
            $grouped[$name] ??= ['table' => $referencedTable, 'columns' => [], 'referenced' => [], 'delete' => $onDelete, 'update' => $onUpdate];
            $grouped[$name]['columns'][] = $column;
            $grouped[$name]['referenced'][] = $referencedColumn;
        }

        return array_values(array_map(
            fn (string|int $name, array $constraint): ForeignKeyConstraintData => new ForeignKeyConstraintData(
                (string) $name,
                $constraint['columns'],
                $constraint['table'],
                $constraint['referenced'],
                ForeignKeyConstraintData::rule($constraint['delete']),
                ForeignKeyConstraintData::rule($constraint['update']),
            ),
            array_keys($grouped),
            $grouped,
        ));
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
