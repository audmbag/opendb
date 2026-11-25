<?php

declare(strict_types=1);

namespace App\Database\Schema;

use Illuminate\Database\Connection;

/**
 * The columns, indexes and foreign keys of every table of a schema, read in one query of each kind.
 *
 * Laravel describes a table with three queries: over a remote server, the hundred tables of a schema took three
 * hundred round trips. The queries here are those of the grammars of Laravel without the filter on the table, which
 * is returned instead, so that their rows go through the same processors. SQLite, a local file, keeps the queries of
 * Laravel: its pragmas only read one table at a time.
 *
 * @phpstan-type TableDescription array{columns: list<array<string, mixed>>, indexes: list<array<string, mixed>>, foreign_keys: list<array<string, mixed>>}
 */
class SchemaIntrospector
{
    /**
     * @param  list<string>  $tables
     * @return array<string, TableDescription>|null the description of each table, or null when the driver reads one table at a time
     */
    public function describeAll(Connection $connection, string $schema, array $tables): ?array
    {
        $queries = $this->queries($connection);

        if ($queries === null || $tables === []) {
            return $queries === null ? null : [];
        }

        $processor = $connection->getPostProcessor();
        $columns = $this->byTable($connection->selectFromWriteConnection($queries['columns'], [$schema]));
        $indexes = $this->byTable($connection->selectFromWriteConnection($queries['indexes'], [$schema]));
        $foreignKeys = $this->byTable($connection->selectFromWriteConnection($queries['foreign_keys'], $queries['foreign_keys_bindings'] === 2 ? [$schema, $schema] : [$schema]));

        $descriptions = [];

        foreach ($tables as $table) {
            $descriptions[$table] = [
                'columns' => $processor->processColumns($columns[$table] ?? []),
                'indexes' => $processor->processIndexes($indexes[$table] ?? []),
                'foreign_keys' => $processor->processForeignKeys($foreignKeys[$table] ?? []),
            ];
        }

        return $descriptions;
    }

    /**
     * The comments of the tables and of their columns, keyed `table` and `table.column` as Truss annotates them.
     *
     * @param  list<array<string, mixed>>  $tables  the tables as the schema builder of Laravel lists them
     * @param  array<string, TableDescription>  $descriptions
     * @return array<string, string>
     */
    public function comments(array $tables, array $descriptions): array
    {
        $comments = [];

        foreach ($tables as $table) {
            $name = (string) $table['name'];

            if ($this->present($table['comment'] ?? null)) {
                $comments[$name] = (string) $table['comment'];
            }

            foreach ($descriptions[$name]['columns'] ?? [] as $column) {
                if ($this->present($column['comment'] ?? null)) {
                    $comments["{$name}.{$column['name']}"] = (string) $column['comment'];
                }
            }
        }

        return $comments;
    }

    /**
     * @return array{columns: string, indexes: string, foreign_keys: string, foreign_keys_bindings: int}|null
     */
    protected function queries(Connection $connection): ?array
    {
        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $this->mysqlQueries(),
            'pgsql' => $this->postgresQueries((string) $connection->getServerVersion()),
            default => null,
        };
    }

    /**
     * @return array{columns: string, indexes: string, foreign_keys: string, foreign_keys_bindings: int}
     */
    protected function mysqlQueries(): array
    {
        return [
            'columns' => 'select table_name as `table`, column_name as `name`, data_type as `type_name`, column_type as `type`, '
                .'collation_name as `collation`, is_nullable as `nullable`, '
                .'column_default as `default`, column_comment as `comment`, '
                .'generation_expression as `expression`, extra as `extra` '
                .'from information_schema.columns where table_schema = ? '
                .'order by table_name, ordinal_position asc',
            'indexes' => 'select table_name as `table`, index_name as `name`, group_concat(column_name order by seq_in_index) as `columns`, '
                .'index_type as `type`, not non_unique as `unique` '
                .'from information_schema.statistics where table_schema = ? '
                .'group by table_name, index_name, index_type, non_unique '
                .'order by table_name, index_name',
            'foreign_keys' => 'select kc.table_name as `table`, kc.constraint_name as `name`, '
                .'group_concat(kc.column_name order by kc.ordinal_position) as `columns`, '
                .'kc.referenced_table_schema as `foreign_schema`, '
                .'kc.referenced_table_name as `foreign_table`, '
                .'group_concat(kc.referenced_column_name order by kc.ordinal_position) as `foreign_columns`, '
                .'rc.update_rule as `on_update`, '
                .'rc.delete_rule as `on_delete` '
                .'from information_schema.key_column_usage kc join information_schema.referential_constraints rc '
                .'on kc.constraint_schema = rc.constraint_schema and kc.constraint_name = rc.constraint_name and kc.table_name = rc.table_name '
                .'where kc.table_schema = ? and kc.referenced_table_name is not null and rc.constraint_schema = ? '
                .'group by kc.table_name, kc.constraint_name, kc.referenced_table_schema, kc.referenced_table_name, rc.update_rule, rc.delete_rule '
                .'order by kc.table_name, kc.constraint_name',
            'foreign_keys_bindings' => 2,
        ];
    }

    /**
     * Only the tables listed by Laravel (`r` and `p`) are read: the namespace also holds the indexes and the sequences.
     *
     * @return array{columns: string, indexes: string, foreign_keys: string, foreign_keys_bindings: int}
     */
    protected function postgresQueries(string $serverVersion): array
    {
        return [
            'columns' => 'select c.relname as "table", a.attname as name, t.typname as type_name, format_type(a.atttypid, a.atttypmod) as type, '
                .(version_compare($serverVersion, '9.1', '<')
                    ? 'null as collation, '
                    : '(select tc.collcollate from pg_catalog.pg_collation tc where tc.oid = a.attcollation) as collation, ')
                .'not a.attnotnull as nullable, '
                .'(select pg_get_expr(adbin, adrelid) from pg_attrdef where c.oid = pg_attrdef.adrelid and pg_attrdef.adnum = a.attnum) as default, '
                .(version_compare($serverVersion, '12.0', '<') ? "'' as generated, " : 'a.attgenerated as generated, ')
                .'col_description(c.oid, a.attnum) as comment '
                .'from pg_attribute a, pg_class c, pg_type t, pg_namespace n '
                ."where n.nspname = ? and c.relkind in ('r', 'p') and a.attnum > 0 and a.attrelid = c.oid and a.atttypid = t.oid and n.oid = c.relnamespace "
                .'order by c.relname, a.attnum',
            'indexes' => 'select tc.relname as "table", ic.relname as name, string_agg(a.attname, \',\' order by indseq.ord) as columns, '
                .'am.amname as "type", i.indisunique as "unique", i.indisprimary as "primary" '
                .'from pg_index i '
                .'join pg_class tc on tc.oid = i.indrelid '
                .'join pg_namespace tn on tn.oid = tc.relnamespace '
                .'join pg_class ic on ic.oid = i.indexrelid '
                .'join pg_am am on am.oid = ic.relam '
                .'join lateral unnest(i.indkey) with ordinality as indseq(num, ord) on true '
                .'left join pg_attribute a on a.attrelid = i.indrelid and a.attnum = indseq.num '
                .'where tn.nspname = ? '
                .'group by tc.relname, ic.relname, am.amname, i.indisunique, i.indisprimary '
                .'order by tc.relname, ic.relname',
            'foreign_keys' => 'select tc.relname as "table", c.conname as name, '
                ."string_agg(la.attname, ',' order by conseq.ord) as columns, "
                .'fn.nspname as foreign_schema, fc.relname as foreign_table, '
                ."string_agg(fa.attname, ',' order by conseq.ord) as foreign_columns, "
                .'c.confupdtype as on_update, c.confdeltype as on_delete '
                .'from pg_constraint c '
                .'join pg_class tc on c.conrelid = tc.oid '
                .'join pg_namespace tn on tn.oid = tc.relnamespace '
                .'join pg_class fc on c.confrelid = fc.oid '
                .'join pg_namespace fn on fn.oid = fc.relnamespace '
                .'join lateral unnest(c.conkey) with ordinality as conseq(num, ord) on true '
                .'join pg_attribute la on la.attrelid = c.conrelid and la.attnum = conseq.num '
                .'join pg_attribute fa on fa.attrelid = c.confrelid and fa.attnum = c.confkey[conseq.ord] '
                ."where c.contype = 'f' and tn.nspname = ? "
                .'group by tc.relname, c.conname, fn.nspname, fc.relname, c.confupdtype, c.confdeltype '
                .'order by tc.relname, c.conname',
            'foreign_keys_bindings' => 1,
        ];
    }

    /**
     * The rows of each table, without the column naming it, in the order the query gave them.
     *
     * @param  array<int, object>  $rows
     * @return array<string, list<object>>
     */
    protected function byTable(array $rows): array
    {
        $tables = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $table = (string) $row['table'];
            unset($row['table']);

            $tables[$table][] = (object) $row;
        }

        return $tables;
    }

    private function present(mixed $comment): bool
    {
        return $comment !== null && trim((string) $comment) !== '';
    }
}
