<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use Illuminate\Support\Collection;
use Throwable;

class PostgresSQLDriver extends BaseDriver implements DriverContract
{
    public function label(): string
    {
        return 'pgsql';
    }

    public function getConnectionString(bool $escapePassword = true): string
    {
        $config = $this->getConfig();

        $password = $escapePassword ? '[password]' : $config['password'];

        return "pgsql://{$config['username']}:{$password}@{$config['host']}:{$config['port']}/{$config['database']}";
    }

    public function describeDatabase(): array
    {
        return $this->remember('database', function () {
            $row = $this->connection->selectOne(
                'SELECT current_database() AS database, version() AS version,
                        pg_encoding_to_char(encoding) AS charset
                 FROM pg_database WHERE datname = current_database()'
            );

            $tableCount = $this->connection->selectOne(
                "SELECT COUNT(*) AS count FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE c.relkind = 'r' AND n.nspname = 'public'"
            )->count;

            return [
                'driver' => 'pgsql',
                'database' => $row->database,
                'version' => $row->version,
                'charset' => $row->charset,
                'table_count' => (int) $tableCount,
            ];
        });
    }

    public function describeTables(?string $table = null): array
    {
        return $this->remember($table ? "tables:{$table}" : 'tables', function () use ($table) {
            $tablesQuery = "SELECT c.relname AS table_name, obj_description(c.oid) AS table_comment,
                                c.reltuples::bigint AS row_estimate
                         FROM pg_class c
                         JOIN pg_namespace n ON n.oid = c.relnamespace
                         WHERE c.relkind = 'r' AND n.nspname = 'public'";
            $tablesBindings = [];

            $columnsQuery = "SELECT table_name, column_name, data_type, is_nullable, column_default, ordinal_position
                          FROM information_schema.columns
                          WHERE table_schema = 'public'";
            $columnsBindings = [];

            $pkQuery = "SELECT tc.table_name, kcu.column_name
                     FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu
                       ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
                     WHERE tc.constraint_type = 'PRIMARY KEY' AND tc.table_schema = 'public'";
            $pkBindings = [];

            if ($table !== null) {
                $tablesQuery .= ' AND c.relname = ?';
                $tablesBindings[] = $table;

                $columnsQuery .= ' AND table_name = ?';
                $columnsBindings[] = $table;

                $pkQuery .= ' AND tc.table_name = ?';
                $pkBindings[] = $table;
            }

            $tablesQuery .= ' ORDER BY c.relname';
            $columnsQuery .= ' ORDER BY table_name, ordinal_position';

            $tables = $this->connection->select($tablesQuery, $tablesBindings);
            $columns = $this->connection->select($columnsQuery, $columnsBindings);
            $primaryKeys = $this->connection->select($pkQuery, $pkBindings);

            return $this->assemble($tables, $columns, $primaryKeys);
        });
    }

    protected function assemble(array $tables, array $columns, array $primaryKeys): array
    {
        $columnsByTable = Collection::make($columns)->groupBy('table_name');
        $pkByTable = Collection::make($primaryKeys)
            ->groupBy('table_name')
            ->map(fn ($rows) => $rows->pluck('column_name')->all());

        return Collection::make($tables)->map(function ($table) use ($columnsByTable, $pkByTable) {
            $pk = $pkByTable->get($table->table_name, []);

            return [
                'table' => $table->table_name,
                'row_estimate' => $table->row_estimate !== null ? (int) $table->row_estimate : null,
                'comment' => $table->table_comment ?: null,
                'columns' => $columnsByTable->get($table->table_name, Collection::make())->map(fn ($c) => [
                    'name' => $c->column_name,
                    'type' => $c->data_type,
                    'nullable' => $c->is_nullable === 'YES',
                    'default' => $c->column_default,
                    'key' => in_array($c->column_name, $pk, true) ? 'PRI' : null,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    public function describeRelations(?string $table = null): array
    {
        return $this->remember($table ? "relations:{$table}" : 'relations', function () use ($table) {
            $query = "SELECT tc.constraint_name, tc.table_name, kcu.column_name,
                          ccu.table_name AS referenced_table, ccu.column_name AS referenced_column,
                          rc.update_rule, rc.delete_rule
                   FROM information_schema.table_constraints tc
                   JOIN information_schema.key_column_usage kcu
                     ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
                   JOIN information_schema.constraint_column_usage ccu
                     ON tc.constraint_name = ccu.constraint_name AND tc.table_schema = ccu.table_schema
                   JOIN information_schema.referential_constraints rc
                     ON tc.constraint_name = rc.constraint_name AND tc.table_schema = rc.constraint_schema
                   WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public'";
            $bindings = [];

            if ($table !== null) {
                $query .= ' AND tc.table_name = ?';
                $bindings[] = $table;
            }

            $query .= ' ORDER BY tc.table_name, tc.constraint_name, kcu.ordinal_position';

            $rows = $this->connection->select($query, $bindings);

            return Collection::make($rows)
                ->groupBy('constraint_name')
                ->map(function (Collection $rows) {
                    $first = $rows->first();

                    return [
                        'name' => $first->constraint_name,
                        'table' => $first->table_name,
                        'referenced_table' => $first->referenced_table,
                        'update_rule' => $first->update_rule,
                        'delete_rule' => $first->delete_rule,
                        'columns' => $rows->map(fn ($r) => [
                            'column' => $r->column_name,
                            'referenced_column' => $r->referenced_column,
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all();
        });
    }

    /**
     * @throws Throwable
     */
    public function executeReadOnly(string $sql): array
    {
        $this->connection->statement('BEGIN TRANSACTION READ ONLY');

        try {
            $data = $this->connection->select($sql);
            $this->connection->statement('COMMIT');
            return $data;
        } catch (Throwable $e) {
            $this->connection->statement('ROLLBACK');
            throw $e;
        }
    }
}
