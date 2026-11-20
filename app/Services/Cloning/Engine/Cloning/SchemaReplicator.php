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
use App\Services\Cloning\Engine\SqlDump\Dialects\AbstractDumpDialect;
use App\Services\Cloning\Engine\SqlDump\DumpDialectFactory;
use Illuminate\Support\Facades\DB;
use PDO;
use stdClass;
use Throwable;

/**
 * Crée dans la cible les tables qui lui manquent, et complète celles qui existent.
 *
 * Une table est d'abord créée avec la définition de la source quand les deux bases sont de la même famille (SHOW
 * CREATE TABLE de MySQL et MariaDB, DDL enregistré par SQLite) ; sinon, et si la cible la refuse, elle est écrite par
 * le dialecte de la cible, avec ses index et sa colonne auto-incrémentée.
 */
class SchemaReplicator
{
    private const array MYSQL_FAMILY = [DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB];

    public function __construct(
        private readonly SchemaInspector $inspector,
        private readonly DatabaseConnectionService $connector,
        private readonly DumpDialectFactory $dialects = new DumpDialectFactory,
    ) {}

    /**
     * @param  ?callable(string, array<string, mixed>): void  $onWarning  Reçoit un événement du journal qui n'empêche pas la copie (index refusé).
     * @param  list<string>  $createdTables  Reçoit les tables créées, dont addForeignKeys() ajoutera les clés étrangères.
     * @return array<string, string> Erreur de chaque table qui n'a pas pu être créée
     */
    public function replicate(
        ConnectionData $source,
        ConnectionData $target,
        DatabaseSchemaData $sourceSchema,
        array $tables,
        bool $enforceColumnTypes,
        bool $dropUnknownTables,
        bool $dropExtraColumns = false,
        ?callable $onWarning = null,
        array &$createdTables = [],
    ): array {
        $targetConnName = $this->connector->open($target);
        $sourceConnName = null;
        $dialect = $this->dialect($target);
        $failedTables = [];

        try {
            $targetSchema = $this->inspector->inspect($target);

            if ($source->type === DatabaseConnectionType::PostgreSQL && $target->type === DatabaseConnectionType::PostgreSQL) {
                $sourceConnName = $this->connector->open($source);
                $this->copyEnumTypes($sourceConnName, $targetConnName);
            }

            foreach ($tables as $tableName) {
                $sourceTable = $sourceSchema->getTable($tableName);

                if (! $sourceTable instanceof TableSchemaData) {
                    continue;
                }

                $targetTable = $targetSchema->getTable($tableName);

                if (! $targetTable instanceof TableSchemaData) {
                    $error = $this->createTable($source, $target, $sourceTable, $targetConnName, $sourceConnName, $dialect, $onWarning);

                    if ($error !== null) {
                        $failedTables[$tableName] = $error;
                    } else {
                        $createdTables[] = $tableName;
                    }

                    continue;
                }

                $targetColNames = array_map(static fn (ColumnSchemaData $c): string => $c->name, $targetTable->columns);

                if ($enforceColumnTypes) {
                    foreach ($sourceTable->columns as $col) {
                        if (! in_array($col->name, $targetColNames, true)) {
                            DB::connection($targetConnName)->statement(sprintf(
                                'ALTER TABLE %s ADD COLUMN %s',
                                $dialect->quoteIdentifier($tableName),
                                $dialect->columnDefinition($col, $sourceTable, $source->type),
                            ));
                        }
                    }
                }

                if ($dropExtraColumns) {
                    $sourceColNames = array_map(static fn (ColumnSchemaData $c): string => $c->name, $sourceTable->columns);

                    foreach ($targetTable->columns as $col) {
                        if (! in_array($col->name, $sourceColNames, true)) {
                            DB::connection($targetConnName)->statement(sprintf('ALTER TABLE %s DROP COLUMN %s', $dialect->quoteIdentifier($tableName), $dialect->quoteIdentifier($col->name)));
                        }
                    }
                }
            }

            if ($dropUnknownTables) {
                $sourceTableNames = array_map(static fn (TableSchemaData $t): string => $t->name, $sourceSchema->tables);

                foreach ($targetSchema->tables as $targetTable) {
                    if (! in_array($targetTable->name, $sourceTableNames, true)) {
                        DB::connection($targetConnName)->statement('DROP TABLE IF EXISTS '.$dialect->quoteIdentifier($targetTable->name));
                    }
                }
            }
        } finally {
            DB::purge($targetConnName);

            if ($sourceConnName !== null) {
                DB::purge($sourceConnName);
            }
        }

        return $failedTables;
    }

    /**
     * Ajoute aux tables créées leurs clés étrangères, une fois les lignes copiées et sans les relire : celles qui visent
     * une table absente de la cible sont laissées de côté. SQLite les a déjà reçues dans CREATE TABLE.
     *
     * @param  list<string>  $tables  Tables créées par replicate().
     * @param  ?callable(string, array<string, mixed>): void  $onWarning
     */
    public function addForeignKeys(ConnectionData $source, ConnectionData $target, DatabaseSchemaData $sourceSchema, array $tables, string $targetConnName, ?callable $onWarning = null): void
    {
        $dialect = $this->dialect($target);
        $schema = DB::connection($targetConnName)->getSchemaBuilder();

        foreach ($tables as $tableName) {
            $table = $sourceSchema->getTable($tableName);

            if (! $table instanceof TableSchemaData || $table->constraints === []) {
                continue;
            }

            $constraints = [];

            foreach ($table->constraints as $constraint) {
                $referenced = $sourceSchema->getTable($constraint->referencedTable);
                $columns = $constraint->referencedColumns ?? $referenced?->primaryKey ?? [];

                if ($columns !== [] && $schema->hasTable($constraint->referencedTable)) {
                    $constraints[] = $constraint->referencing($columns);
                }
            }

            $withConstraints = new TableSchemaData($table->name, $table->columns, $table->foreignKeys, $table->indexes, $table->primaryKey, $constraints);

            foreach ($dialect->addForeignKeys($withConstraints, $source->type) as $statement) {
                try {
                    DB::connection($targetConnName)->statement($statement);
                } catch (Throwable $throwable) {
                    if ($onWarning !== null) {
                        $onWarning('foreign_key_creation_failed', ['table' => $tableName, 'error' => $throwable->getMessage()]);
                    }
                }
            }
        }
    }

    /**
     * Fait repartir la colonne auto-incrémentée de la cible après la plus grande valeur copiée, pour que les lignes
     * ajoutées ensuite par l'application ne heurtent pas les identifiants copiés.
     *
     * @param  ?string  $connectionName  Connexion déjà ouverte sur la cible, sinon une nouvelle est ouverte puis fermée.
     */
    public function correctAutoIncrement(ConnectionData $target, string $tableName, string $pkColumn, ?string $connectionName = null): void
    {
        if (! in_array($target->type, [...self::MYSQL_FAMILY, DatabaseConnectionType::PostgreSQL], true)) {
            return;
        }

        $connName = $connectionName ?? $this->connector->open($target);
        $dialect = $this->dialect($target);
        $quotedTable = $dialect->quoteIdentifier($tableName);
        $quotedCol = $dialect->quoteIdentifier($pkColumn);

        try {
            if ($target->type === DatabaseConnectionType::PostgreSQL) {
                // Sans séquence (colonne ni serial ni identity), pg_get_serial_sequence() renvoie NULL et setval() rien.
                DB::connection($connName)->select(
                    "SELECT setval(pg_get_serial_sequence(?, ?), COALESCE(MAX({$quotedCol}), 0) + 1, false) FROM {$quotedTable}",
                    [$quotedTable, $pkColumn],
                );

                return;
            }

            $rows = DB::connection($connName)->select('SELECT COALESCE(MAX('.$quotedCol.'), 0) + 1 AS next_val FROM '.$quotedTable);
            $row = (array) ($rows[0] ?? new stdClass);
            $rawVal = $row['next_val'] ?? 1;
            $nextVal = max(1, is_numeric($rawVal) ? (int) $rawVal : 1);

            DB::connection($connName)->statement('ALTER TABLE '.$quotedTable.' AUTO_INCREMENT = '.$nextVal);
        } finally {
            if ($connectionName === null) {
                DB::purge($connName);
            }
        }
    }

    /**
     * @return ?string L'erreur de la création, null si la table a été créée
     */
    private function createTable(
        ConnectionData $source,
        ConnectionData $target,
        TableSchemaData $table,
        string $targetConnName,
        ?string &$sourceConnName,
        AbstractDumpDialect $dialect,
        ?callable $onWarning,
    ): ?string {
        foreach ($this->nativeDefinitions($source, $target, $table->name, $sourceConnName) as $statements) {
            if ($this->runAll($targetConnName, $statements, $dialect->quoteIdentifier($table->name))) {
                return null;
            }
        }

        // Un type que la cible ne connaît pas (domaine, extension) se rabat sur la correspondance du dialecte.
        $definitions = [$table, new TableSchemaData(
            $table->name,
            array_map(fn (ColumnSchemaData $column): ColumnSchemaData => $column->withoutDeclaredType(), $table->columns),
            $table->foreignKeys,
            $table->indexes,
            $table->primaryKey,
            $table->constraints,
        )];

        $error = null;

        foreach ($definitions as $definition) {
            try {
                DB::connection($targetConnName)->statement($dialect->createTable($definition, true, $source->type));
            } catch (Throwable $throwable) {
                $error ??= $throwable->getMessage();

                continue;
            }

            $unportable = $dialect->unportableDefaults($definition, $source->type);

            if ($unportable !== [] && $onWarning !== null) {
                $onWarning('default_not_copied', ['table' => $table->name, 'columns' => $unportable]);
            }

            foreach ($dialect->createIndexes($definition, $source->type) as $statement) {
                try {
                    DB::connection($targetConnName)->statement($statement);
                } catch (Throwable $throwable) {
                    if ($onWarning !== null) {
                        $onWarning('index_creation_failed', ['table' => $table->name, 'error' => $throwable->getMessage()]);
                    }
                }
            }

            return null;
        }

        return $error;
    }

    /**
     * Définitions de la source que la cible peut reprendre telles quelles, à essayer dans l'ordre.
     *
     * @return list<list<string>>
     */
    private function nativeDefinitions(ConnectionData $source, ConnectionData $target, string $tableName, ?string &$sourceConnName): array
    {
        $mysqlFamily = in_array($source->type, self::MYSQL_FAMILY, true) && in_array($target->type, self::MYSQL_FAMILY, true);
        $sqlite = $source->type === DatabaseConnectionType::Sqlite && $target->type === DatabaseConnectionType::Sqlite;

        if (! $mysqlFamily && ! $sqlite) {
            return [];
        }

        try {
            $sourceConnName ??= $this->connector->open($source);

            return $mysqlFamily ? $this->mysqlDefinitions($sourceConnName, $tableName) : $this->sqliteDefinitions($sourceConnName, $tableName);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * SHOW CREATE TABLE sans clés étrangères ; puis sans collations, que MySQL et MariaDB ne partagent pas toutes
     * (utf8mb4_0900_ai_ci, utf8mb4_uca1400_ai_ci).
     *
     * @return list<list<string>>
     */
    private function mysqlDefinitions(string $sourceConnName, string $tableName): array
    {
        $rows = DB::connection($sourceConnName)->select('SHOW CREATE TABLE `'.str_replace('`', '``', $tableName).'`');
        $ddl = ((array) ($rows[0] ?? []))['Create Table'] ?? null;

        if (! is_string($ddl)) {
            return [];
        }

        $ddl = $this->sanitiseNativeDdl($ddl);

        return [[$ddl], [preg_replace('/\s+COLLATE[= ]\s*\w+/i', '', $ddl) ?? $ddl]];
    }

    /**
     * La table et ses index tels que SQLite les a enregistrés.
     *
     * @return list<list<string>>
     */
    private function sqliteDefinitions(string $sourceConnName, string $tableName): array
    {
        $statement = DB::connection($sourceConnName)->getPdo()->prepare("SELECT type, sql FROM sqlite_master WHERE tbl_name = ? AND type IN ('table', 'index') AND sql IS NOT NULL ORDER BY type = 'index'");
        $statement->execute([$tableName]);
        $statements = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as ['type' => $type, 'sql' => $sql]) {
            $statements[] = $type === 'table'
                ? preg_replace('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?/i', 'CREATE TABLE IF NOT EXISTS ', (string) $sql)
                : preg_replace('/^CREATE\s+(UNIQUE\s+)?INDEX\s+(IF\s+NOT\s+EXISTS\s+)?/i', 'CREATE $1INDEX IF NOT EXISTS ', (string) $sql);
        }

        return $statements === [] ? [] : [$statements];
    }

    /**
     * Exécute les requêtes ; si l'une échoue, retire la table à moitié créée pour laisser la place à la suivante.
     *
     * @param  list<string>  $statements
     */
    private function runAll(string $connName, array $statements, string $quotedTable): bool
    {
        try {
            foreach ($statements as $statement) {
                DB::connection($connName)->statement($statement);
            }

            return true;
        } catch (Throwable) {
            rescue(fn () => DB::connection($connName)->statement('DROP TABLE IF EXISTS '.$quotedTable), report: false);

            return false;
        }
    }

    /**
     * Les types énumérés du schéma de la source, que ses colonnes déclarent et que la cible doit connaître.
     */
    private function copyEnumTypes(string $sourceConnName, string $targetConnName): void
    {
        $enums = DB::connection($sourceConnName)->select(
            "SELECT t.typname AS name, string_agg(quote_literal(e.enumlabel), ', ' ORDER BY e.enumsortorder) AS labels
            FROM pg_type t
            JOIN pg_enum e ON e.enumtypid = t.oid
            JOIN pg_namespace n ON n.oid = t.typnamespace
            WHERE n.nspname = current_schema()
            GROUP BY t.typname"
        );

        foreach ($enums as $enum) {
            $exists = DB::connection($targetConnName)->selectOne(
                'SELECT 1 AS found FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace WHERE t.typname = ? AND n.nspname = current_schema()',
                [$enum->name],
            );

            if ($exists === null) {
                rescue(fn () => DB::connection($targetConnName)->statement('CREATE TYPE "'.str_replace('"', '""', $enum->name).'" AS ENUM ('.$enum->labels.')'), report: false);
            }
        }
    }

    private function dialect(ConnectionData $target): AbstractDumpDialect
    {
        $dialect = $this->dialects->make($target->type);

        assert($dialect instanceof AbstractDumpDialect);

        return $dialect;
    }

    private function sanitiseNativeDdl(string $ddl): string
    {
        $ddl = preg_replace('/,?\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+(?:DELETE|UPDATE)\s+\w+(?:\s+(?!ON\b)\w+)?)*/i', '', $ddl) ?? $ddl;

        $ddl = preg_replace('/,(\s*\))/m', '$1', $ddl) ?? $ddl;

        $ddl = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $ddl) ?? $ddl;

        return preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $ddl) ?? $ddl;
    }
}
