<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use Illuminate\Database\Connection;
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

    /**
     * PostgreSQL impose de se connecter à une base : on passe par les bases de maintenance.
     *
     * @return array<int, string>
     */
    protected function serverDatabases(): array
    {
        return ['postgres', 'template1'];
    }

    protected function databaseExists(Connection $server): bool
    {
        return $server->selectOne(
            'SELECT 1 AS found FROM pg_database WHERE datname = ?',
            [$this->databaseName()]
        ) !== null;
    }

    protected function canCreateDatabase(Connection $server): bool
    {
        $role = $server->selectOne(
            'SELECT (rolcreatedb OR rolsuper) AS allowed FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->allowed ?? false);
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

    /**
     * @throws Throwable
     */
    public function executeReadOnly(string $sql, array $bindings = []): array
    {
        $this->connection->statement('BEGIN TRANSACTION READ ONLY');

        try {
            $data = $this->connection->select($sql, $bindings);
            $this->connection->statement('COMMIT');

            return $data;
        } catch (Throwable $e) {
            $this->connection->statement('ROLLBACK');
            throw $e;
        }
    }
}
