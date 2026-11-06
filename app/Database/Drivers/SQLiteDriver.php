<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\DatabaseCheckException;
use App\Database\ExplainOutput;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\File;

class SQLiteDriver extends BaseDriver implements DriverContract
{
    public function getConnectionString(): string
    {
        $config = $this->getConfig();

        return "sqlite://{$config['database']}";
    }

    /**
     * Pas de serveur avec SQLite : la base peut être créée si le dossier parent est accessible en écriture.
     */
    protected function checkDatabaseAvailability(): void
    {
        $path = $this->databaseName();

        $this->database_missing = $path !== ':memory:' && ! is_file($path);

        if (! $this->database_missing) {
            $this->connect();
            $this->getConnection()->getPdo();

            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw DatabaseCheckException::cannotCreate($path, "le dossier [{$directory}] n'est pas accessible en écriture");
        }
    }

    protected function performCreateDatabase(): void
    {
        if (File::put($this->databaseName(), '') === false) {
            throw DatabaseCheckException::creationFailed($this->databaseName());
        }
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

    /**
     * The statements SQLite kept when the table and its indexes were created.
     */
    public function showCreateTable(string $table): string
    {
        $statements = $this->connection->select(
            "SELECT sql FROM sqlite_master WHERE tbl_name = ? AND sql IS NOT NULL ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name",
            [$table],
        );

        return collect($statements)->map(fn (object $row): string => rtrim((string) $row->sql, ';').';')->implode("\n\n");
    }

    public function executeReadOnly(string $sql, array $bindings = []): array
    {
        $this->assertQueryAllowed($sql);

        $this->connection->statement('PRAGMA query_only = ON;');

        try {
            return $this->connection->select($sql, $bindings);
        } finally {
            $this->connection->statement('PRAGMA query_only = OFF;');
        }
    }

    public function explain(string $sql, array $bindings = []): ExplainOutput
    {
        $this->assertQueryAllowed($sql);

        return $this->inRolledBackReadOnlyTransaction(function (Connection $connection) use ($sql, $bindings): ExplainOutput {
            $rows = array_map(fn (object $row): array => (array) $row, $connection->select("EXPLAIN QUERY PLAN {$sql}", $bindings));

            return new ExplainOutput(
                raw: $rows,
                classic: ExplainOutput::table(array_map(fn (array $row): object => (object) $row, $rows), ['id', 'parent', 'detail']),
                version: (string) $connection->getServerVersion(),
            );
        });
    }

    /**
     * SQLite n'a pas de transaction en lecture seule : le pragma query_only refuse toute écriture le temps du callback.
     */
    protected function inRolledBackReadOnlyTransaction(Closure $callback): mixed
    {
        $this->connection->statement('PRAGMA query_only = ON;');

        try {
            return $callback($this->connection);
        } finally {
            $this->connection->statement('PRAGMA query_only = OFF;');
        }
    }
}
