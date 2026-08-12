<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use Illuminate\Support\Collection;

class SQLiteDriver extends BaseDriver implements DriverContract
{
    public function getConnectionString(): string
    {
        $config = $this->getConfig();

        return "sqlite://{$config['database']}";
    }

    public function describeDatabase(): array
    {
        return $this->remember('database', function () {
            $version = $this->connection->selectOne('SELECT sqlite_version() AS version')->version;

            $tableCount = $this->connection->selectOne(
                "SELECT COUNT(*) AS count FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            )->count;

            $path = $this->connection->getConfig('database');

            return [
                'driver' => 'sqlite',
                'database' => $path === ':memory:' ? ':memory:' : basename((string) $path),
                'version' => $version,
                'charset' => null,
                'table_count' => (int) $tableCount,
            ];
        });
    }

    public function describeTables(?string $table = null): array
    {
        return $this->remember($table ? "tables:{$table}" : 'tables', function () use ($table) {
            $query = 'SELECT m.name AS table_name, p.name AS column_name, p.type AS data_type,
                          p."notnull" AS not_null, p.dflt_value AS column_default, p.pk AS is_pk
                   FROM sqlite_master m
                   JOIN pragma_table_info(m.name) p
                   WHERE m.type = \'table\' AND m.name NOT LIKE \'sqlite_%\'';
            $bindings = [];

            if ($table !== null) {
                $query .= ' AND m.name = ?';
                $bindings[] = $table;
            }

            $query .= ' ORDER BY m.name, p.cid';

            $rows = $this->connection->select($query, $bindings);

            return Collection::make($rows)
                ->groupBy('table_name')
                ->map(fn ($columns, $tableName) => [
                    'table' => $tableName,
                    'columns' => $columns->map(fn ($c) => [
                        'name' => $c->column_name,
                        'type' => $c->data_type,
                        'nullable' => ! $c->not_null,
                        'default' => $c->column_default,
                        'key' => $c->is_pk ? 'PRI' : null,
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });
    }

    public function describeRelations(?string $table = null): array
    {
        return $this->remember($table ? "relations:{$table}" : 'relations', function () use ($table) {
            $query = 'SELECT m.name AS table_name, fk."table" AS referenced_table,
                          fk."from" AS column_name, fk."to" AS referenced_column,
                          fk.on_update, fk.on_delete, fk.id AS constraint_group
                   FROM sqlite_master m
                   JOIN pragma_foreign_key_list(m.name) fk
                   WHERE m.type = \'table\' AND m.name NOT LIKE \'sqlite_%\'';
            $bindings = [];

            if ($table !== null) {
                $query .= ' AND m.name = ?';
                $bindings[] = $table;
            }

            $query .= ' ORDER BY m.name, fk.id, fk.seq';

            $rows = $this->connection->select($query, $bindings);

            return Collection::make($rows)
                ->groupBy(fn ($r) => "{$r->table_name}:{$r->constraint_group}")
                ->map(function (Collection $rows) {
                    $first = $rows->first();

                    return [
                        'name' => null, // SQLite ne nomme pas les contraintes FK
                        'table' => $first->table_name,
                        'referenced_table' => $first->referenced_table,
                        'update_rule' => $first->on_update,
                        'delete_rule' => $first->on_delete,
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

    public function executeReadOnly(string $sql): array
    {
        $this->connection->statement('PRAGMA query_only = ON;');

        try {
            return $this->connection->select($sql);
        } finally {
            $this->connection->statement('PRAGMA query_only = OFF;');
        }
    }
}
