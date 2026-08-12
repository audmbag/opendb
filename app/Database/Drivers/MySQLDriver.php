<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use Illuminate\Support\Collection;
use Throwable;

class MySQLDriver extends BaseDriver implements DriverContract
{
    public function getConnectionString(bool $escapePassword = true): string
    {
        $config = $this->getConfig();

        $password = $escapePassword ? '[password]' : $config['password'];

        return "mysql://{$config['username']}:{$password}@{$config['host']}:{$config['port']}/{$config['database']}";
    }

    public function describeDatabase(): array
    {
        return $this->remember('database', function () {
            $database = $this->connection->getDatabaseName();

            $row = $this->connection->selectOne(
                'SELECT VERSION() AS version, @@character_set_database AS charset'
            );

            $tableCount = $this->connection->selectOne(
                'SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = ?',
                [$database]
            )->count;

            return [
                'driver' => $this->label(),
                'database' => $database,
                'version' => $row->version,
                'charset' => $row->charset,
                'table_count' => (int) $tableCount,
            ];
        });
    }

    public function describeTables(?string $table = null): array
    {
        return $this->remember($table ? "tables:{$table}" : 'tables', function () use ($table) {
            $database = $this->connection->getDatabaseName();

            $tablesQuery = "SELECT table_name, engine, table_rows, table_comment
                         FROM information_schema.tables
                         WHERE table_schema = ? AND table_type = 'BASE TABLE'";
            $tablesBindings = [$database];

            $columnsQuery = 'SELECT table_name, column_name, column_type, is_nullable, column_default,
                                 column_key, extra, ordinal_position
                          FROM information_schema.columns
                          WHERE table_schema = ?';
            $columnsBindings = [$database];

            if ($table !== null) {
                $tablesQuery .= ' AND table_name = ?';
                $tablesBindings[] = $table;

                $columnsQuery .= ' AND table_name = ?';
                $columnsBindings[] = $table;
            }

            $tablesQuery .= ' ORDER BY table_name';
            $columnsQuery .= ' ORDER BY table_name, ordinal_position';

            $tables = $this->connection->select($tablesQuery, $tablesBindings);
            $columns = $this->connection->select($columnsQuery, $columnsBindings);

            return $this->assemble($tables, $columns);
        });
    }

    protected function assemble(array $tables, array $columns): array
    {
        $normalize = function (array $data) {
            return array_map(
                fn ($item) => (object) array_change_key_case((array) $item),
                $data
            );
        };

        $tables = $normalize($tables);
        $columns = $normalize($columns);

        $columnsByTable = Collection::make($columns)->groupBy('table_name');

        return Collection::make($tables)->map(fn ($table) => [
            'table' => $table->table_name,
            'engine' => $table->engine,
            'row_estimate' => $table->table_rows !== null ? (int) $table->table_rows : null,
            'comment' => $table->table_comment ?: null,
            'columns' => $columnsByTable->get($table->table_name, Collection::make())->map(fn ($c) => [
                'name' => $c->column_name,
                'type' => $c->column_type,
                'nullable' => $c->is_nullable === 'YES',
                'default' => $c->column_default,
                'key' => $c->column_key ?: null,
                'extra' => $c->extra ?: null,
            ])->values()->all(),
        ])->values()->all();
    }

    public function describeRelations(): array
    {
        return $this->remember('relations', function () {
            $database = $this->connection->getDatabaseName();

            $foreignKeys = $this->connection->select(
                'SELECT
                kcu.constraint_name,
                kcu.table_name,
                kcu.column_name,
                kcu.referenced_table_name,
                kcu.referenced_column_name,
                rc.update_rule,
                rc.delete_rule
             FROM information_schema.key_column_usage kcu
             JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = kcu.constraint_name
                AND rc.constraint_schema = kcu.table_schema
             WHERE kcu.table_schema = ?
                AND kcu.referenced_table_name IS NOT NULL
             ORDER BY kcu.table_name, kcu.constraint_name, kcu.ordinal_position',
                [$database]
            );

            return $this->assembleRelations($foreignKeys);
        });
    }

    protected function assembleRelations(array $foreignKeys): array
    {
        $normalize = fn (array $data) => array_map(
            fn ($item) => (object) array_change_key_case((array) $item),
            $data
        );

        $foreignKeys = $normalize($foreignKeys);

        return Collection::make($foreignKeys)
            ->groupBy('constraint_name')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'name' => $first->constraint_name,
                    'table' => $first->table_name,
                    'referenced_table' => $first->referenced_table_name,
                    'update_rule' => $first->update_rule,
                    'delete_rule' => $first->delete_rule,
                    'columns' => $rows->map(fn ($r) => [
                        'column' => $r->column_name,
                        'referenced_column' => $r->referenced_column_name,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @throws Throwable
     */
    public function executeReadOnly(string $sql): array
    {
        $this->connection->statement('START TRANSACTION READ ONLY');

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
