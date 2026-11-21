<?php

namespace App\Database\Drivers;

use AlbertoArena\Truss\Cache\SchemaCacheRepository;
use AlbertoArena\Truss\Export\ExportBuilder;
use AlbertoArena\Truss\TrussManager;
use App\Database\Contracts\DriverContract;
use App\Database\Enums\DriverLabEnum;
use App\Database\Exceptions\DatabaseCheckException;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\PrivilegeException;
use App\Database\HiddenTableGuard;
use App\Database\QueryPrivilegeGuard;
use App\Database\Schema\TableDefinitionBuilder;
use App\Enums\PrivilegeEnum;
use App\Services\Privileges\Data\TablePrivileges;
use App\Services\SSH\Remote;
use App\Services\SSH\SshTunnel;
use App\Services\SSH\TunnelUsage;
use Closure;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BaseDriver
{
    const int SCHEMA_CACHE_TTL = 60;

    protected Connection $connection;

    protected bool $has_ssh_tunnel = false;

    public ?int $ssh_tunnel_forward_port = null;

    public ?string $connection_name = null;

    protected bool $database_missing = false;

    public readonly string $driver_id;

    public readonly ?string $host;

    public readonly ?string $port;

    public readonly ?string $username;

    public readonly ?string $password;

    public readonly string $database;

    /**
     * @var list<string>
     */
    protected array $hiddenTables = [];

    /**
     * The privileges of the user the driver acts for, or null for OpenDB itself.
     */
    protected ?TablePrivileges $privileges = null;

    protected string $privilegedUser = '';

    /**
     * The tables the user holds no privilege on, out of their sight like the hidden ones.
     *
     * @var list<string>
     */
    protected array $tablesOutOfReach = [];

    public function __construct(private array $databaseConfig)
    {
        $this->fill();

        $this->connection_name = $this->createConnectionName();

        $this->has_ssh_tunnel = isset($this->databaseConfig['ssh']) && ! empty($this->databaseConfig['ssh']);

        if (! $this->isSQLite()) {
            $this->databaseConfig['host'] = self::cleanHost($this->databaseConfig['host']);
        }

        if ($this->has_ssh_tunnel) {
            $this->startSSHTunnel();
        }

        $this->connect();

        $this->connection = $this->getConnection();
    }

    private function fill(): void
    {
        $this->driver_id = $this->databaseConfig['driver_id'];
        $this->host = ($this->databaseConfig['host'] ?? null) ? self::cleanHost($this->databaseConfig['host']) : null;
        $this->port = $this->databaseConfig['port'] ?? null;
        $this->username = $this->databaseConfig['username'] ?? null;
        $this->password = $this->databaseConfig['password'] ?? null;
        $this->database = $this->databaseConfig['database'];
    }

    protected function createConnectionName(): string
    {
        return static::connectionNameFor($this->databaseConfig);
    }

    /**
     * The name of the connection of a database, known without connecting to it, like the key of its cached schema.
     *
     * @param  array<string, mixed>  $config
     */
    public static function connectionNameFor(array $config): string
    {
        if ($config['driver_id'] === DriverLabEnum::SQLITE->value) {
            $database_key = str_replace(['/', '\\', '.'], '_', (string) $config['database']);
        } else {
            $host = filled($config['host'] ?? null) ? self::cleanHost($config['host']) : null;
            $database_key = str_replace(['.', ':'], '_', $host.'_'.($config['port'] ?? null).'_'.$config['database']);
        }

        return strtolower(config('app.name')).'_'.$database_key;
    }

    /**
     * Forward a local port to the database through the SSH master of its server, held until the process ends so that
     * the master is not closed while it is in use.
     */
    protected function startSSHTunnel(): void
    {
        $tunnel = static::sshTunnelFor($this->databaseConfig);

        $this->ssh_tunnel_forward_port = $tunnel->start();

        $usage = app(TunnelUsage::class);
        $usage->hold($tunnel->target());

        register_shutdown_function(static function () use ($usage, $tunnel): void {
            try {
                $usage->release($tunnel->target());
            } catch (Throwable) {
                // The cache is gone with the process: the hold is forgotten after its delay.
            }
        });
    }

    /**
     * The tunnel to the database through its SSH server, not started yet.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when the SSH configuration is incomplete, or its key cannot be read
     */
    public static function sshTunnelFor(array $config): SshTunnel
    {
        $sshConfig = $config['ssh'];

        array_map(function (string $key) use ($sshConfig) {
            if (! ($sshConfig[$key] ?? false)) {
                throw new InvalidArgumentException(
                    "SSH configuration is missing required key: {$key}"
                );
            }
        }, ['host', 'port', 'user', 'id_file']);

        return new SshTunnel(
            sshHost: $sshConfig['host'],
            remote: Remote::tcp(
                remotePort: (int) $config['port'],
                remoteHost: self::cleanHost((string) $config['host'])
            ),
            sshUser: $sshConfig['user'],
            sshPort: (int) $sshConfig['port'],
            identityFile: $sshConfig['id_file'],
            autoDisconnectTimeout: '15m'
        );
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
                __('database.errors.no_driver', ['driver' => $config['driver_id']])
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
     * Teste la connexion. Si `create_database_if_not_exists` est actif, seule la connexion au serveur est
     * validée, puis on vérifie que la base existe ou que l'utilisateur a le droit de la créer.
     *
     * @throws Exception
     */
    public function testConnection(bool $expectedException = true): bool
    {
        try {
            if ($this->shouldCreateDatabase()) {
                $this->checkDatabaseAvailability();
            } else {
                $this->getConnection()->getPdo();
            }

            return true;
        } catch (Exception $e) {
            if ($expectedException) {
                throw $e;
            }

            return false;
        } finally {
            $this->disconnect();
        }
    }

    public function shouldCreateDatabase(): bool
    {
        return (bool) ($this->databaseConfig['create_database_if_not_exists'] ?? false);
    }

    /**
     * Indique si la dernière vérification a conclu que la base n'existe pas encore.
     */
    public function willCreateDatabase(): bool
    {
        return $this->shouldCreateDatabase() && $this->database_missing;
    }

    /**
     * Crée la base de données si elle n'existe pas.
     *
     * @return bool `true` si la base a été créée, `false` si elle existait déjà.
     */
    public function createDatabaseIfNotExists(): bool
    {
        try {
            $this->checkDatabaseAvailability();

            if (! $this->database_missing) {
                return false;
            }

            $this->performCreateDatabase();

            $this->connect();
            $this->getConnection()->getPdo();

            return true;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Vérifie que la base est utilisable ou, à défaut, qu'elle peut être créée par l'utilisateur.
     *
     * @throws Exception
     */
    protected function checkDatabaseAvailability(): void
    {
        $server = $this->connectToServer();

        try {
            $this->database_missing = ! $this->databaseExists($server);

            if (! $this->database_missing) {
                $this->connect();
                $this->getConnection()->getPdo();

                return;
            }

            if (! $this->canCreateDatabase($server)) {
                throw DatabaseCheckException::cannotCreate($this->databaseName());
            }
        } finally {
            $this->disconnectFromServer();
        }
    }

    /**
     * @throws Exception
     */
    protected function performCreateDatabase(): void
    {
        $server = $this->connectToServer();

        try {
            if (! $server->getSchemaBuilder()->createDatabase($this->databaseName())) {
                throw DatabaseCheckException::creationFailed($this->databaseName());
            }
        } finally {
            $this->disconnectFromServer();
        }
    }

    /**
     * Bases sur lesquelles tenter la connexion « niveau serveur » (sans la base cible), dans l'ordre.
     *
     * @return array<int, string|null>
     */
    protected function serverDatabases(): array
    {
        return [null];
    }

    protected function serverConnectionName(): string
    {
        return $this->connection_name.'_server';
    }

    /**
     * Ouvre une connexion au serveur sans passer par la base cible, ce qui valide uniquement
     * l'hôte, le port et les identifiants.
     *
     * @throws Exception
     */
    protected function connectToServer(): Connection
    {
        $name = $this->serverConnectionName();
        $firstError = null;

        foreach ($this->serverDatabases() as $database) {
            Config::set('database.connections.'.$name, [...$this->getConfig(), 'database' => $database]);

            DB::purge($name);

            try {
                $connection = DB::connection($name);
                $connection->getPdo();

                return $connection;
            } catch (Exception $e) {
                $firstError ??= $e;
                $this->disconnectFromServer();
            }
        }

        throw $firstError;
    }

    protected function disconnectFromServer(): void
    {
        $name = $this->serverConnectionName();

        DB::purge($name);

        config(['database.connections.'.$name => null]);
    }

    protected function databaseExists(Connection $server): bool
    {
        throw DatabaseCheckException::unsupported('databaseExists', $this->label());
    }

    protected function canCreateDatabase(Connection $server): bool
    {
        throw DatabaseCheckException::unsupported('canCreateDatabase', $this->label());
    }

    public function getConnection(): Connection
    {
        return DB::connection($this->connection_name);
    }

    public function sshTunnelPort(): ?int
    {
        return $this->has_ssh_tunnel ? $this->ssh_tunnel_forward_port : null;
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

        if (! $this->isSQLite() && $this->has_ssh_tunnel) {
            $config['host'] = '127.0.0.1';
            $config['port'] = $this->ssh_tunnel_forward_port;
        }

        unset($config['driver_id'], $config['ssh'], $config['create_database_if_not_exists']);

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
            // The journal of the database of OpenDB is no choice to make for a database of the user: SQLite keeps it in
            // the file, for every program opening it afterwards.
            DriverLabEnum::SQLITE->value => [
                ...config('database.connections.sqlite'),
                'journal_mode' => null,
                'synchronous' => null,
                'database' => $config['database'],
            ],
            default => throw new RuntimeException('Unsupported driver: '.$driver),
        };
    }

    protected function databaseName(): string
    {
        return $this->databaseConfig['database'];
    }

    public static function cleanHost(string $host): string
    {
        return $host === 'localhost' ? '127.0.0.1' : $host;
    }

    /**
     * @param  list<string>  $tables
     */
    public function hideTables(array $tables): static
    {
        $this->hiddenTables = array_values($tables);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function hiddenTables(): array
    {
        return array_values(array_unique([...$this->hiddenTables, ...$this->tablesOutOfReach]));
    }

    public function withPrivileges(TablePrivileges $privileges, string $user): static
    {
        $this->privileges = $privileges;
        $this->privilegedUser = $user;
        $this->tablesOutOfReach = $privileges->reachesEveryTable() ? [] : $privileges->hiddenAmong($this->allTableNames());

        return $this;
    }

    public function privileges(): ?TablePrivileges
    {
        return $this->privileges;
    }

    /**
     * @throws PrivilegeException when the privileges of the user do not allow the query.
     * @throws HiddenTableException when the query could reach a hidden table.
     */
    public function assertQueryAllowed(string $sql): void
    {
        if ($this->privileges !== null) {
            QueryPrivilegeGuard::assertAllowed($sql, $this->privileges, $this->allTableNames(...), $this->privilegedUser, [$this->databaseName()]);
        }

        HiddenTableGuard::assertQueryAllowed($sql, $this->hiddenTables());
    }

    /**
     * @throws PrivilegeException when the user does not hold the privilege on the table, or on the whole project without a table.
     */
    public function assertPrivilege(PrivilegeEnum $privilege, ?string $table = null): void
    {
        if ($this->privileges === null || $this->privileges->allows($privilege, $table)) {
            return;
        }

        throw $table === null
            ? PrivilegeException::deniedOnProject($privilege, $this->privilegedUser)
            : PrivilegeException::denied($privilege, $this->privilegedUser, $table);
    }

    /**
     * Check a privilege that is not about the statement itself, like EXPORT for the rows a query reads: it is needed on every
     * table the query spells, or on the whole project.
     *
     * @throws PrivilegeException when the user does not hold the privilege on one of the tables.
     */
    public function assertPrivilegeOnQuery(PrivilegeEnum $privilege, string $sql): void
    {
        if ($this->privileges === null || $this->privileges->allowsOnProject($privilege)) {
            return;
        }

        $cited = array_filter($this->allTableNames(), fn (string $table): bool => HiddenTableGuard::cites($sql, $table));

        if ($cited === []) {
            $this->assertPrivilege($privilege);
        }

        foreach ($cited as $table) {
            $this->assertPrivilege($privilege, $table);
        }
    }

    /**
     * Every table of the database, those out of sight included.
     *
     * @return list<string>
     */
    private function allTableNames(): array
    {
        return array_values(array_map(
            fn (array $table): string => (string) $table['name'],
            app(TrussManager::class)->snapshot()->connection($this->connection_name)->toArray(),
        ));
    }

    private function getExportBuilder(): ExportBuilder
    {
        return app(TrussManager::class)->snapshot()->connection($this->connection_name)->except($this->hiddenTables());
    }

    /**
     * The tables without their foreign keys referencing a hidden table, which could not be followed.
     *
     * @param  list<array<string, mixed>>  $tables
     * @return list<array<string, mixed>>
     */
    private function withoutHiddenReferences(array $tables): array
    {
        $hiddenTables = $this->hiddenTables();

        if ($hiddenTables === []) {
            return $tables;
        }

        return array_map(fn (array $table): array => [
            ...$table,
            'foreign_keys' => array_values(array_filter(
                $table['foreign_keys'] ?? [],
                fn (array $foreignKey): bool => ! in_array($foreignKey['references_table'] ?? null, $hiddenTables, true),
            )),
        ], $tables);
    }

    public function statement(string $sql, mixed $bindings): bool
    {
        $this->assertQueryAllowed($sql);

        return $this->connection->statement($sql, $bindings);
    }

    public function dryRunAffectedRows(string $sql, array $bindings = []): ?int
    {
        $this->assertQueryAllowed($sql);

        if (! preg_match('/^\s*(UPDATE|DELETE)\b/i', $sql)) {
            return null;
        }

        $this->connection->beginTransaction();

        try {
            return $this->connection->affectingStatement($sql, $bindings);
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * Exécute le callback dans une transaction en lecture seule toujours annulée : rien de ce qu'il fait ne peut rester.
     *
     * @template T
     *
     * @param  Closure(Connection): T  $callback
     * @return T
     */
    protected function inRolledBackReadOnlyTransaction(Closure $callback): mixed
    {
        $this->connection->statement('START TRANSACTION READ ONLY');

        try {
            return $callback($this->connection);
        } finally {
            $this->connection->statement('ROLLBACK');
        }
    }

    public function listTables(bool $withDetails = true, ?callable $mapper = null): array
    {
        $tables = $this->withoutHiddenReferences($this->getExportBuilder()->toArray());

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

        return $this->withoutHiddenReferences($table->toArray());
    }

    public function forgetSchema(): void
    {
        app(SchemaCacheRepository::class)->forget($this->connection_name);
    }

    public function getSchema($withoutAnnotations = true): array
    {
        $builder = $this->getExportBuilder();
        if ($withoutAnnotations) {
            $builder = $builder->withoutAnnotations();
        }

        return $this->withoutHiddenReferences($builder->toArray());
    }

    public function exportSchema(string $format): string
    {
        $acceptedFormats = ['dbml', 'json', 'csv', 'markdown', 'mermaid', 'llm'];

        if (! in_array($format, $acceptedFormats)) {
            $format = 'dbml';
        }

        return $this->getExportBuilder()->render($format);
    }

    public function estimatedRowCount(string $table): ?int
    {
        return null;
    }

    public function showCreateTable(string $table): string
    {
        return app(TableDefinitionBuilder::class)->build($this->connection, $table);
    }

    public function connectionId(): ?string
    {
        return null;
    }

    public function cancel(string $connectionId): bool
    {
        return false;
    }

    /**
     * PostgreSQL et SQLite annulent aussi le DDL : une transaction annulée défait tout.
     *
     * @param  list<string>  $statements
     */
    public function canRollBack(array $statements): bool
    {
        return true;
    }

    public function isSQLite(): bool
    {
        return $this->databaseConfig['driver_id'] === DriverLabEnum::SQLITE->value;
    }
}
