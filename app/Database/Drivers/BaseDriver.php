<?php

namespace App\Database\Drivers;

use AlbertoArena\Truss\Export\ExportBuilder;
use AlbertoArena\Truss\TrussManager;
use App\Database\Contracts\DriverContract;
use App\Database\Enums\DriverLabEnum;
use App\Services\SSH\Remote;
use App\Services\SSH\SshTunnel;
use Closure;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class BaseDriver
{
    const int SCHEMA_CACHE_TTL = 60;

    protected Connection $connection;

    protected bool $has_ssh_tunnel = false;

    public ?int $ssh_tunnel_forward_port = null;

    public ?string $connection_name = null;

    public function __construct(private array $databaseConfig)
    {
        $this->connection_name = $this->createConnectionName();

        $this->has_ssh_tunnel = isset($this->databaseConfig['ssh']) && ! empty($this->databaseConfig['ssh']);

        $this->databaseConfig['host'] = $this->cleanHost($this->databaseConfig['host']);

        if ($this->has_ssh_tunnel) {
            $this->startSSHTunnel();
        }

        $this->connect();

        $this->connection = $this->getConnection();
    }

    protected function createConnectionName(): string
    {
        return strtolower(config('app.name')).'_'.str_replace(['.', ':'], '_', $this->databaseConfig['host']).'_'.$this->databaseConfig['port'].'_'.$this->databaseConfig['database'];
    }

    protected function startSSHTunnel(): void
    {
        $sshConfig = $this->databaseConfig['ssh'];

        array_map(function (string $key) use ($sshConfig) {
            if (! ($sshConfig[$key] ?? false)) {
                throw new InvalidArgumentException(
                    "SSH configuration is missing required key: {$key}"
                );
            }
        }, ['host', 'port', 'user', 'id_file']);

        $tunnel = new SshTunnel(
            sshHost: $sshConfig['host'],
            remote: Remote::tcp(
                remotePort: $this->databaseConfig['port'],
                remoteHost: $this->databaseConfig['host']
            ),
            sshUser: $sshConfig['user'],
            sshPort: $sshConfig['port'],
            identityFile: $sshConfig['id_file'],
            autoDisconnectTimeout: '15m'
        );

        $this->ssh_tunnel_forward_port = $tunnel->start();
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

    public function connect(): void
    {
        Config::set('database.connections.'.$this->connection_name, $this->getConfig());

        Config::set('truss.connections.'.$this->connection_name, [
            'excluded_tables' => [],
        ]);

        Config::set('truss.excluded_tables', []);

        DB::purge($this->connection_name);

        DB::reconnect($this->connection_name);
    }

    /**
     * @throws Exception
     */
    public function testConnection(): void
    {
        $this->getConnection()->getPdo();
    }

    public function getConnection(): Connection
    {
        return DB::connection($this->connection_name);
    }

    public function disconnect(): void
    {
        DB::disconnect($this->connection_name);

        DB::purge($this->connection_name);

        config(['database.connections.'.$this->connection_name => null]);
    }

    protected function getConfig(): array
    {
        $config = $this->databaseConfig;

        $driver = $config['driver_id'] ?? $this->label();

        $config['port'] = $this->has_ssh_tunnel ? $this->ssh_tunnel_forward_port : $config['port'];

        unset($config['driver_id']);
        unset($config['ssh']);

        return match ($driver) {
            DriverLabEnum::MYSQL->value => [
                ...config('database.connections.mysql'),
                ...$config,
            ],
            DriverLabEnum::POSTGRESQL->value => [
                ...config('database.connections.pgsql'),
                ...$config,
            ],
            DriverLabEnum::MARIADB->value => [
                ...config('database.connections.mariadb'),
                ...$config,
            ],
            DriverLabEnum::SQLITE->value => [
                ...config('database.connections.sqlite'),
                'database' => $config['database'],
            ],
            default => throw new RuntimeException('Unsupported driver: '.$driver),
        };
    }

    protected function cleanHost(string $host): string
    {
        return $host === 'localhost' ? '127.0.0.1' : $host;
    }

    protected function remember(string $suffix, Closure $callback): mixed
    {
        $host = $this->cleanHost($this->getConfig()['host']);

        $database = $this->connection->getDatabaseName();

        $key = "opendb:schema:{$this->connection->getDriverName()}:{$host}:{$database}:{$suffix}";

        return Cache::remember($key, self::SCHEMA_CACHE_TTL, $callback);
    }

    private function getExportBuilder(): ExportBuilder
    {
        return app(TrussManager::class)->snapshot()->connection($this->connection_name);
    }

    public function statement(string $sql, mixed $bindings): bool
    {
        return $this->connection->statement($sql, $bindings);
    }

    public function listTables(bool $withDetails = true, ?callable $mapper = null): array
    {
        $tables = $this->getExportBuilder()->toArray();

        return array_map(function (array $table) use ($mapper, $withDetails): string|array {
            if ($mapper) {
                return $mapper($table);
            }

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
