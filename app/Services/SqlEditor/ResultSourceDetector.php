<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Database\Contracts\DriverContract;
use App\Services\QueryPlan\SqlClauses;

/**
 * The table whose rows a SELECT of the editor returns as they are, so that its cells can be changed in the result:
 * a single table, without join, grouping, distinct rows or combined queries, read by columns named as they are,
 * its primary key among them. Anything else is not editable: guessing wrong would change another column or row.
 */
class ResultSourceDetector
{
    private const string NOT_SINGLE_TABLE = '/\b(?:JOIN|UNION|INTERSECT|EXCEPT|WITH|GROUP\s+BY|HAVING|DISTINCT|INTO|FOR\s+UPDATE)\b/i';

    private const string PLAIN_COLUMN = '/^(?:[\w$]+|`[^`]+`|"[^"]+")(?:\.(?:[\w$]+|`[^`]+`|"[^"]+"|\*))?$|^\*$/u';

    /**
     * @param  list<string>  $columns  the names of the columns of the result
     * @return ?array{table: string, primaryKey: list<string>}
     */
    public function detect(DriverContract $driver, string $statement, array $columns): ?array
    {
        $sql = (string) preg_replace('/--[^\n]*|\/\*.*?\*\//s', ' ', SqlClauses::maskStrings($statement));

        if (! preg_match('/^\s*SELECT\b/i', $sql) || preg_match(self::NOT_SINGLE_TABLE, $sql)) {
            return null;
        }

        // A comma or a subquery in FROM reads several tables.
        if (! preg_match('/\bFROM\s+(.+?)(?=\bWHERE\b|\bORDER\b|\bLIMIT\b|\bOFFSET\b|\bFETCH\b|;|$)/is', $sql, $from) || preg_match('/[,(]/', $from[1])) {
            return null;
        }

        $clauses = new SqlClauses($statement);
        $tables = array_unique(array_values($clauses->tables()));
        $items = array_map(trim(...), explode(',', (string) $clauses->selectList()));

        if (count($tables) !== 1 || array_any($items, fn (string $item): bool => preg_match(self::PLAIN_COLUMN, $item) !== 1)) {
            return null;
        }

        $definition = array_find($driver->getSchema(), fn (array $table): bool => strcasecmp($table['name'], array_first($tables)) === 0);

        if ($definition === null || $definition['primary_key'] === [] || count(array_unique($columns)) !== count($columns)) {
            return null;
        }

        $tableColumns = array_column($definition['columns'], 'name');

        if (array_diff($columns, $tableColumns) !== [] || array_diff($definition['primary_key'], $columns) !== []) {
            return null;
        }

        return ['table' => $definition['name'], 'primaryKey' => $definition['primary_key']];
    }
}
