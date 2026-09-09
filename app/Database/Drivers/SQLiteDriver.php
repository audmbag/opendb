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

    public function executeReadOnly(string $sql, array $bindings = []): array
    {
        $this->connection->statement('PRAGMA query_only = ON;');

        try {
            return $this->connection->select($sql, $bindings);
        } finally {
            $this->connection->statement('PRAGMA query_only = OFF;');
        }
    }
}
