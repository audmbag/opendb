<?php

namespace App\Database\Drivers;

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

    public function label(): string
    {
        return (string) str(class_basename(static::class))->beforeLast('Driver')->lower();
    }

    public function __construct(private readonly array $databaseConfig)
    {
        $this->connect();

        $this->connection = $this->getConnection();
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
}
