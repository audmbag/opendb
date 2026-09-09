<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
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

    /**
     * @throws Throwable
     */
    public function executeReadOnly(string $sql, array $bindings = []): array
    {
        $this->connection->statement('START TRANSACTION READ ONLY');

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
