<?php

namespace App\Database\Drivers;

use AlbertoArena\Truss\Export\ExportBuilder;
use AlbertoArena\Truss\TrussManager;
use App\Database\Contracts\DriverContract;
use App\Database\Enums\DriverLabEnum;
use Closure;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BaseDriver
{
    const string CONNECTION_NAME = 'dynamic';

    const int SCHEMA_CACHE_TTL = 60;

    protected Connection $connection;

    public function __construct(private readonly array $databaseConfig)
    {
        $this->connect();

        $this->connection = $this->getConnection();
    }

    public function label(): string
    {
        return (string) str(class_basename(static::class))->beforeLast('Driver')->lower();
    }

    public static function for(array $config): DriverContract
    {
        return match ($config['driver_id']) {
            DriverLabEnum::MYSQL->value => new MySQLDriver($config),
            DriverLabEnum::SQLITE->value => new SQLiteDriver($config),
            DriverLabEnum::POSTGRESQL->value => new PostgresSQLDriver($config),
            DriverLabEnum::MARIADB->value => new MariaDBDriver($config),
            default => throw new RuntimeException(
                "Aucun driver disponible pour [{$config['driver_id']}]."
            ),
        };
    }

    public function getConfig(): array
    {
        return $this->buildConfig($this->databaseConfig);
    }

    public function connect(): void
    {
        Config::set('database.connections.'.self::CONNECTION_NAME, $this->getConfig());

        Config::set('truss.connections.'.self::CONNECTION_NAME, [
            'excluded_tables' => [],
        ]);

        Config::set('truss.excluded_tables', []);

        DB::purge(self::CONNECTION_NAME);

        DB::reconnect(self::CONNECTION_NAME);
    }

    public function testConnection(): true|string
    {
        try {
            $this->getConnection()->getPdo();

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getConnection(): Connection
    {
        return DB::connection(self::CONNECTION_NAME);
    }

    public function disconnect(): void
    {
        DB::disconnect(self::CONNECTION_NAME);

        DB::purge(self::CONNECTION_NAME);

        config(['database.connections.'.self::CONNECTION_NAME => null]);
    }

    protected function buildConfig(array $config): array
    {
        $host = ($config['host'] ?? 'localhost') === 'localhost' ? '127.0.0.1' : $config['host'];
        $driver = $config['driver_id'] ?? $this->label();
        unset($config['driver_id']);

        return match ($driver) {
            DriverLabEnum::MYSQL->value => [
                ...config('database.connections.mysql'),
                ...$config,
                'host' => $host,
            ],
            DriverLabEnum::POSTGRESQL->value => [
                ...config('database.connections.pgsql'),
                ...$config,
                'host' => $host,
            ],
            DriverLabEnum::MARIADB->value => [
                ...config('database.connections.mariadb'),
                ...$config,
                'host' => $host,
            ],
            DriverLabEnum::SQLITE->value => [
                ...config('database.connections.sqlite'),
                'database' => $config['database'],
            ],
            default => throw new RuntimeException('Unsupported driver: '.$driver),
        };
    }

    protected function remember(string $suffix, Closure $callback): mixed
    {
        $host = $this->connection->getConfig('host') ?? 'local';
        $database = $this->connection->getDatabaseName();
        $key = "opendb:schema:{$this->connection->getDriverName()}:{$host}:{$database}:{$suffix}";

        return Cache::remember($key, self::SCHEMA_CACHE_TTL, $callback);
    }

    private function getExportBuilder(): ExportBuilder
    {
        return app(TrussManager::class)->snapshot()->connection(self::CONNECTION_NAME);
    }

    public function statement(string $sql, mixed $bindings): bool
    {
        return $this->connection->statement($sql, $bindings);
    }

    public function listTables(bool $withDetails = true): array
    {
        $tables = $this->getExportBuilder()->toArray();

        return array_map(function (array $table) use ($withDetails): string {

            if (! $withDetails) {
                return $table['name'];
            }

            $columns = count($table['columns'] ?? []);
            $hasPk = ($table['primary_key'] ?? []) !== [] ? 'has PK' : 'no PK';
            $fks = count($table['foreign_keys'] ?? []);

            return "{$table['name']}: {$columns} columns, {$hasPk}, {$fks} FK".($fks === 1 ? '' : 's');
        }, $tables);
    }

    public function focusTable(string $tableName, bool $withRelatedTables = true): array
    {
        $table = $this->getExportBuilder()->only([$tableName]);

        if ($withRelatedTables) {
            $table = $this->getExportBuilder()->focus($tableName, 1);
        }

        return $table->toArray();
    }

    public function getSchema(): array
    {
        return $this->getExportBuilder()->toArray();
    }

    public function exportSchema(string $format): string
    {
        $acceptedFormats = ['dbml', 'json', 'csv', 'markdown', 'mermaid', 'llm'];

        if (! in_array($format, $acceptedFormats)) {
            $format = 'dbml';
        }

        return $this->getExportBuilder()->render($format);
    }
}
