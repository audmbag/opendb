<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use PDO;
use Throwable;

/**
 * Trouve la clé par laquelle lire une table de la source par tranches : sa clé primaire dans l'ordre de son index,
 * ou le rowid d'une table SQLite.
 */
final readonly class TableKeyResolver
{
    /**
     * Types comparés octet par octet, qu'un paramètre lié en chaîne de caractères comparerait mal.
     */
    private const array BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bytea'];

    public function __construct(private PDO $pdo, private DatabaseConnectionType $type) {}

    /**
     * Null si la table n'a pas de clé utilisable : elle est alors lue d'un seul tenant.
     */
    public function resolve(TableSchemaData $table): ?TableKey
    {
        try {
            return $this->type === DatabaseConnectionType::Sqlite ? $this->sqliteKey($table) : $this->primaryKey($table);
        } catch (Throwable) {
            return null;
        }
    }

    private function primaryKey(TableSchemaData $table): ?TableKey
    {
        $columns = array_filter($table->columns, fn (ColumnSchemaData $column): bool => $column->isPrimary);

        if ($columns === [] || array_any($columns, fn (ColumnSchemaData $column): bool => in_array(strtolower($column->type), self::BINARY_TYPES, true))) {
            return null;
        }

        $ordered = $this->primaryKeyOrder($table->name);

        // L'ordre de l'index évite à la base de trier toute la table à chaque tranche.
        $names = $ordered !== [] && count($ordered) === count($columns) ? $ordered : array_values(array_map(fn (ColumnSchemaData $column): string => $column->name, $columns));

        return new TableKey(array_map(fn (string $name): string => SqlIdentifier::quote($name, $this->type), $names), $names);
    }

    /**
     * @return list<string>
     */
    private function primaryKeyOrder(string $table): array
    {
        $statement = match ($this->type) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => $this->pdo->prepare(
                "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION"
            ),
            DatabaseConnectionType::PostgreSQL => $this->pdo->prepare(
                'SELECT a.attname FROM pg_index i CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, position) JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum WHERE i.indrelid = CAST(? AS regclass) AND i.indisprimary ORDER BY k.position'
            ),
            default => null,
        };

        if ($statement === null) {
            return [];
        }

        $statement->execute([$this->type === DatabaseConnectionType::PostgreSQL ? SqlIdentifier::quote($table, $this->type) : $table]);

        return array_map(strval(...), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Le rowid, sauf pour une table WITHOUT ROWID, dont la clé primaire est forcément renseignée.
     */
    private function sqliteKey(TableSchemaData $table): ?TableKey
    {
        $statement = $this->pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
        $statement->execute([$table->name]);
        $ddl = (string) $statement->fetchColumn();

        if (preg_match('/\bWITHOUT\s+ROWID\b/i', $ddl) === 1) {
            $columns = $this->pdo->query('PRAGMA table_info('.SqlIdentifier::quote($table->name, $this->type).')')->fetchAll(PDO::FETCH_ASSOC)
                |> (fn (array $columns): array => array_filter($columns, fn (array $column): bool => (int) $column['pk'] > 0))
                |> (fn (array $columns): array => array_column($columns, 'name', 'pk'));
            ksort($columns);

            return $columns === [] ? null : new TableKey(
                array_values(array_map(fn (string $name): string => SqlIdentifier::quote($name, $this->type), $columns)),
                array_values($columns),
            );
        }

        // Une colonne peut masquer le rowid sous l'un de ses trois noms.
        $names = array_map(fn (ColumnSchemaData $column): string => strtolower($column->name), $table->columns);
        $alias = array_find(['rowid', '_rowid_', 'oid'], fn (string $alias): bool => ! in_array($alias, $names, true));

        return $alias === null ? null : TableKey::rowid($alias);
    }
}
