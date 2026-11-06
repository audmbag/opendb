<?php

namespace App\Database;

use App\Database\Exceptions\HiddenTableException;

/**
 * Rejects the SQL that could read or change a table the user hid from the agent.
 *
 * Like the other guards, it is a syntax guard, not a SQL parser: any word of the query spelling a hidden table counts,
 * whatever its case, even a column, an alias or a text value, since refusing a query is better than reaching a hidden table.
 * Nothing is rejected while no table is hidden.
 */
class HiddenTableGuard
{
    /**
     * The characters an unquoted identifier is made of, around which the name of a table stops.
     */
    private const string IDENTIFIER_CHARACTER = '[\p{L}\p{N}_$]';

    /**
     * The catalogs describing every table, hidden ones included.
     */
    private const string SYSTEM_CATALOGS = '/(?<![\p{L}\p{N}_$])(?:'
        .'(?:information_schema|performance_schema|pg_catalog'
        .'|pg_(?:class|attribute|attrdef|constraint|index|indexes|tables|views|matviews|statistic|stats|stats_ext|description|namespace|depend|rewrite|trigger|proc)'
        .'|pg_stat\w*|pg_statio\w*|sqlite_(?:master|schema|temp_master|temp_schema|stat\d)|pragma_\w+)(?![\p{L}\p{N}_$])'
        .'|(?:mysql|sys)\s*\.)/iu';

    /**
     * The statements and functions running a query written as text, or naming a table without spelling it.
     */
    private const string DYNAMIC_SQL = '/^\s*DO\b'
        .'|\b(?:PREPARE|EXECUTE(?!\s+(?:FUNCTION|PROCEDURE)\b)|(?:query|cursor|table|schema|database)_to_xml\w*|dblink\w*|(?:to_)?regclass'
        .'|pg_read_file|pg_read_binary_file|load_file)\b'
        .'|\bU&["\']/iu';

    /**
     * @param  list<string>  $hiddenTables
     *
     * @throws HiddenTableException when the query cites a hidden table, reads a system catalog or runs dynamic SQL.
     */
    public static function assertQueryAllowed(string $sql, array $hiddenTables): void
    {
        $exception = static::rejectionOf($sql, $hiddenTables);

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * @param  list<string>  $hiddenTables
     */
    public static function rejects(string $sql, array $hiddenTables): bool
    {
        return static::rejectionOf($sql, $hiddenTables) !== null;
    }

    /**
     * @param  list<string>  $hiddenTables
     *
     * @throws HiddenTableException when the table is hidden.
     */
    public static function assertTableAllowed(string $table, array $hiddenTables): void
    {
        if (in_array($table, $hiddenTables, true)) {
            throw HiddenTableException::named($table);
        }
    }

    /**
     * The reason to reject the query, or null when it may run.
     *
     * @param  list<string>  $hiddenTables
     */
    private static function rejectionOf(string $sql, array $hiddenTables): ?HiddenTableException
    {
        if ($hiddenTables === []) {
            return null;
        }

        $citedTable = array_find($hiddenTables, fn (string $table): bool => static::cites($sql, $table));

        return match (true) {
            $citedTable !== null => HiddenTableException::citedBy($citedTable),
            (bool) preg_match(self::SYSTEM_CATALOGS, $sql) => HiddenTableException::systemCatalog(),
            (bool) preg_match(self::DYNAMIC_SQL, $sql) => HiddenTableException::dynamicSql(),
            default => null,
        };
    }

    private static function cites(string $sql, string $table): bool
    {
        $pattern = '/(?<!'.self::IDENTIFIER_CHARACTER.')'.preg_quote($table, '/').'(?!'.self::IDENTIFIER_CHARACTER.')/iu';

        return (bool) preg_match($pattern, $sql);
    }
}
