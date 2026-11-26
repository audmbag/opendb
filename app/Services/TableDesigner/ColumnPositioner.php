<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Schema\Identifier;
use Illuminate\Database\Connection;

/**
 * The statements giving the columns of a table another order, each column kept as it is.
 *
 * MySQL and MariaDB move a column in place, from the definition they give of it. SQLite cannot: the table is created
 * again from the statement it was created with, its columns in the new order, then its rows, indexes and triggers
 * copied back. PostgreSQL keeps its columns in the order they were added and has no way to change it.
 */
class ColumnPositioner
{
    public function __construct(protected SqliteTableDefinition $sqliteDefinition) {}

    /**
     * Whether the engine can change the order of the columns of a table.
     */
    public function supports(Connection $connection): bool
    {
        return in_array($connection->getDriverName(), ['mysql', 'mariadb', 'sqlite'], true);
    }

    /**
     * The fewest moves turning one order into the other: the longest run of columns already in the right order
     * stays, each other column goes after the one it follows in the new order, from the first to the last.
     *
     * @param  list<string>  $from
     * @param  list<string>  $to  the same columns in their new order
     * @return list<array{column: string, after: ?string}>
     */
    public function moves(array $from, array $to): array
    {
        $positions = array_map(fn (string $column): int => (int) array_search($column, $from, true), $to);
        $staying = array_flip($this->longestIncreasingRun($positions));
        $moves = [];

        foreach ($to as $index => $column) {
            if (! isset($staying[$index])) {
                $moves[] = ['column' => $column, 'after' => $to[$index - 1] ?? null];
            }
        }

        return $moves;
    }

    /**
     * The definition of each column of a MySQL table, word for word as MySQL gives it, keyed by the name of the column.
     *
     * @return array<string, string>
     */
    public function mysqlDefinitions(Connection $connection, string $table): array
    {
        $create = (string) array_values((array) $connection->selectOne('show create table '.Identifier::quote($connection, $table)))[1];
        $definitions = [];

        foreach (explode("\n", $create) as $line) {
            if (preg_match('/^\s*`((?:[^`]|``)+)`\s(.*?),?$/', $line, $match)) {
                $definitions[str_replace('``', '`', $match[1])] = $match[2];
            }
        }

        return $definitions;
    }

    /**
     * MODIFY COLUMN with the definition MySQL gives of the column, so that nothing but its place changes.
     *
     * @param  string  $definition  the definition of the column without its name
     * @param  ?string  $after  the column it follows, null to make it the first one
     */
    public function mysqlMove(Connection $connection, string $table, string $column, string $definition, ?string $after): string
    {
        $position = $after === null ? 'first' : 'after '.Identifier::quote($connection, $after);

        return 'alter table '.Identifier::quote($connection, $table).' modify column '.Identifier::quote($connection, $column)." {$definition} {$position}";
    }

    /**
     * The steps SQLite gives to change a table it cannot alter: a copy with the new structure takes its place, then
     * its indexes and triggers, dropped with it, are created again. The renaming leaves the views alone, the legacy
     * way, or it would refuse the views reading the table while it is missing.
     *
     * @param  list<string>  $order  the names of every column of the table, in their new order
     * @return list<string>
     *
     * @throws TableDesignFailedException
     */
    public function rebuildSqliteTable(Connection $connection, string $table, array $order): array
    {
        $quotedTable = Identifier::quote($connection, $table);
        $temporary = Identifier::quote($connection, "__temp__{$table}");
        $create = (string) $connection->scalar("select sql from sqlite_master where type = 'table' and name = ?", [$table]);

        // Generated columns are computed again: only the stored ones are copied.
        $stored = array_column(array_filter(
            $connection->select('select name, hidden from pragma_table_xinfo(?)', [$table]),
            fn (object $column): bool => (int) $column->hidden === 0,
        ), 'name');
        $copied = implode(', ', array_map(fn (string $name): string => Identifier::quote($connection, $name), array_values(array_intersect($order, $stored))));

        $attached = array_column($connection->select(
            "select sql from sqlite_master where tbl_name = ? and type in ('index', 'trigger') and sql is not null order by rowid",
            [$table],
        ), 'sql');

        // The AUTOINCREMENT counter goes on from where it was, not from the largest identifier left.
        $sequence = $connection->scalar("select count(*) from sqlite_master where name = 'sqlite_sequence'")
            ? $connection->scalar('select seq from sqlite_sequence where name = ?', [$table])
            : null;
        $legacyAlterTable = (bool) $connection->scalar('pragma legacy_alter_table');
        $quotedName = "'".str_replace("'", "''", $table)."'";

        return array_values(array_filter([
            $legacyAlterTable ? null : 'pragma legacy_alter_table = on',
            $this->sqliteDefinition->withColumnOrder($create, $temporary, $order),
            "insert into {$temporary} ({$copied}) select {$copied} from {$quotedTable}",
            "drop table {$quotedTable}",
            "alter table {$temporary} rename to {$quotedTable}",
            $legacyAlterTable ? null : 'pragma legacy_alter_table = off',
            ...$attached,
            $sequence === null ? null : "delete from sqlite_sequence where name = {$quotedName}",
            $sequence === null ? null : 'insert into sqlite_sequence (name, seq) values ('.$quotedName.', '.(int) $sequence.')',
        ]));
    }

    /**
     * The indexes of the longest strictly increasing run of the values.
     *
     * @param  list<int>  $values
     * @return list<int>
     */
    protected function longestIncreasingRun(array $values): array
    {
        $count = count($values);
        $lengths = array_fill(0, $count, 1);
        $previous = array_fill(0, $count, null);

        for ($index = 0; $index < $count; $index++) {
            for ($before = 0; $before < $index; $before++) {
                if ($values[$before] < $values[$index] && $lengths[$index] < $lengths[$before] + 1) {
                    $lengths[$index] = $lengths[$before] + 1;
                    $previous[$index] = $before;
                }
            }
        }

        $run = [];

        for ($index = $count === 0 ? null : array_search(max($lengths), $lengths, true); $index !== null; $index = $previous[$index]) {
            $run[] = $index;
        }

        return array_reverse($run);
    }
}
