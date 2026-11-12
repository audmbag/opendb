<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use App\Database\ExplainOutput;
use Closure;
use Illuminate\Database\Connection;
use Throwable;

class PostgresSQLDriver extends BaseDriver implements DriverContract
{
    public function connectionId(): ?string
    {
        return (string) $this->connection->selectOne('SELECT pg_backend_pid() AS id')->id;
    }

    public function cancel(string $connectionId): bool
    {
        return (bool) $this->connection->selectOne('SELECT pg_cancel_backend(?) AS cancelled', [(int) $connectionId])->cancelled;
    }

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
        $this->assertQueryAllowed($sql);

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

    /**
     * The estimate kept by ANALYZE and autovacuum, -1 for a table never analyzed.
     */
    public function estimatedRowCount(string $table): ?int
    {
        $row = $this->connection->selectOne(
            'SELECT c.reltuples::bigint AS estimate FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relname = ?',
            [$table],
        );

        return isset($row->estimate) && (int) $row->estimate >= 0 ? (int) $row->estimate : null;
    }

    public function explain(string $sql, array $bindings = []): ExplainOutput
    {
        $this->assertQueryAllowed($sql);

        return $this->inRolledBackReadOnlyTransaction(function (Connection $connection) use ($sql, $bindings): ExplainOutput {
            $plans = json_decode((string) array_first((array) $connection->select("EXPLAIN (FORMAT JSON) {$sql}", $bindings)[0]), true);
            $text = array_map(fn (object $row): string => (string) array_first((array) $row), $connection->select("EXPLAIN {$sql}", $bindings));

            return new ExplainOutput(
                raw: $plans,
                classic: ['columns' => null, 'rows' => null, 'text' => implode("\n", $text)],
                version: (string) $connection->getServerVersion(),
                tableRows: $this->estimatedTableRows($connection, $plans),
            );
        });
    }

    protected function inRolledBackReadOnlyTransaction(Closure $callback): mixed
    {
        $this->connection->statement('BEGIN READ ONLY');

        try {
            return $callback($this->connection);
        } finally {
            $this->connection->statement('ROLLBACK');
        }
    }

    /**
     * Le nombre de lignes de chaque table lue par le plan, estimé par les statistiques du moteur : le plan JSON ne donne
     * que les lignes gardées après le filtre. Les tables jamais analysées (reltuples négatif) sont ignorées.
     *
     * @return array<string, int>
     */
    protected function estimatedTableRows(Connection $connection, mixed $plans): array
    {
        $tables = [];

        array_walk_recursive($plans, function (mixed $value, string|int $key) use (&$tables): void {
            if ($key === 'Relation Name' && is_string($value)) {
                $tables[$value] = true;
            }
        });

        if ($tables === []) {
            return [];
        }

        $names = array_keys($tables);
        $rows = $connection->select(
            'SELECT relname, reltuples FROM pg_class WHERE relkind IN (\'r\', \'p\', \'m\') AND relname IN ('.implode(', ', array_fill(0, count($names), '?')).')',
            $names,
        );

        return collect($rows)
            ->filter(fn (object $row): bool => (float) $row->reltuples >= 0)
            ->mapWithKeys(fn (object $row): array => [(string) $row->relname => (int) $row->reltuples])
            ->all();
    }
}
