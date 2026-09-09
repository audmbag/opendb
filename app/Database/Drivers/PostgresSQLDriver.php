<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
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
