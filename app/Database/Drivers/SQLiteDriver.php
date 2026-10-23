<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\DatabaseCheckException;
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

    public function executeReadOnly(string $sql, array $bindings = []): array
    {
        $this->connection->statement('PRAGMA query_only = ON;');

        try {
            return $this->connection->select($sql, $bindings);
        } finally {
            $this->connection->statement('PRAGMA query_only = OFF;');
        }
    }

    public function explain(string $sql, array $bindings = []): array
    {
        $steps = collect($this->executeReadOnly("EXPLAIN QUERY PLAN {$sql}", $bindings))->groupBy('parent');

        $build = function (int $parent) use (&$build, $steps): array {
            return $steps->get($parent, collect())
                ->map(fn (object $step): array => $this->planNode(
                    label: (string) $step->detail,
                    warning: match (true) {
                        (bool) preg_match('/^SCAN (?!.*USING (?:COVERING )?INDEX)/', (string) $step->detail) => 'Parcours complet de la table',
                        str_contains((string) $step->detail, 'USE TEMP B-TREE') => 'Tri ou regroupement dans une structure temporaire',
                        default => null,
                    },
                    children: $build((int) $step->id),
                ))
                ->values()
                ->all();
        };

        return $build(0);
    }
}
