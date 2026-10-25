<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Database;

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\SslConfig;
use App\Services\Cloning\Engine\Database\Tls\CertificateFiles;
use App\Services\Cloning\Engine\Database\Tls\ConnectionErrorHint;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use App\Services\Cloning\Engine\Enums\SslMode;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Pdo\Mysql;
use RuntimeException;
use Throwable;

class DatabaseConnectionService
{
    private const array LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    private const string DOCKER_HOST_GATEWAY = 'host.docker.internal';

    private readonly bool $inDocker;

    private readonly CertificateFiles $certificates;

    public function __construct(?bool $inDocker = null, ?CertificateFiles $certificates = null)
    {

        $this->inDocker = $inDocker ?? is_file('/.dockerenv');
        $this->certificates = $certificates ?? new CertificateFiles;
    }

    public function resolvePassword(ConnectionData $connection): string
    {
        $password = $connection->password;

        if (! str_starts_with($password, 'encrypted:')) {
            return $password;
        }

        try {
            return Crypt::decryptString(substr($password, 10));
        } catch (Throwable) {
            throw new RuntimeException('Could not decrypt password — check APP_KEY.');
        }
    }

    public function buildConfig(ConnectionData $connection, string $password): array
    {
        throw_if($connection->type === DatabaseConnectionType::Dump, RuntimeException::class, 'Dump connections are virtual output targets and have no live database to open.');

        if ($connection->type === DatabaseConnectionType::Sqlite) {
            return [
                'driver' => 'sqlite',
                'database' => $connection->database,
                'prefix' => '',
            ];
        }

        $config = [
            'driver' => $connection->type->value,
            'host' => $this->resolveHost($connection->host),
            'port' => $connection->port,
            'database' => $connection->database,
            'username' => $connection->username,
            'password' => $password,
            'prefix' => '',
        ];

        if ($connection->type === DatabaseConnectionType::Mysql || $connection->type === DatabaseConnectionType::MariaDB) {
            $config['charset'] = 'utf8mb4';
            $config['collation'] = 'utf8mb4_unicode_ci';
        } elseif ($connection->type === DatabaseConnectionType::PostgreSQL) {
            $config['charset'] = 'UTF8';
        }

        if ($connection->schema !== null) {
            $config['search_path'] = $connection->schema;
        }

        return $this->applyTls($config, $connection);
    }

    public function resolvedHost(ConnectionData $connection): ?string
    {
        return $this->resolveHost($connection->host);
    }

    private function resolveHost(?string $host): ?string
    {
        if (! $this->inDocker || $host === null) {
            return $host;
        }

        return in_array(strtolower($host), self::LOOPBACK_HOSTS, true)
        ? self::DOCKER_HOST_GATEWAY
        : $host;
    }

    public function open(ConnectionData $connection): string
    {
        $password = $this->resolvePassword($connection);
        $name = 'clonio_'.uniqid();

        config(['database.connections.'.$name => $this->buildConfig($connection, $password)]);

        try {
            DB::connection($name)->getPdo();
        } catch (Throwable $throwable) {
            DB::purge($name);

            $hint = ConnectionErrorHint::for($throwable, $connection, $this->resolvedHost($connection));

            throw_if($hint === null, $throwable);

            throw new RuntimeException($throwable->getMessage().PHP_EOL.$hint, 0, $throwable);
        }

        return $name;
    }

    public function negotiatedCipher(string $connectionName, DatabaseConnectionType $type): ?string
    {
        $query = match ($type) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => ["SHOW SESSION STATUS LIKE 'Ssl_cipher'", 'Value'],
            DatabaseConnectionType::PostgreSQL => ['SELECT cipher FROM pg_stat_ssl WHERE pid = pg_backend_pid()', 'cipher'],
            default => null,
        };

        if ($query === null) {
            return null;
        }

        [$sql, $column] = $query;

        try {
            $row = DB::connection($connectionName)->selectOne($sql);
        } catch (Throwable) {
            return null;
        }

        $value = is_object($row) ? (get_object_vars($row)[$column] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function applyTls(array $config, ConnectionData $connection): array
    {
        $ssl = $connection->ssl;

        if (! $ssl instanceof SslConfig) {
            return $config;
        }

        $violations = $ssl->violations($connection->type);

        if ($violations !== []) {
            throw new RuntimeException(sprintf('Invalid ssl configuration for connection "%s": %s', $connection->name, implode('; ', $violations)));
        }

        $unreadable = $this->certificates->unreadable($ssl);

        if ($unreadable !== []) {
            throw new RuntimeException('Certificate file not found: '.$unreadable[0]);
        }

        return match ($connection->type) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => $this->applyMysqlTls($config, $ssl),
            DatabaseConnectionType::PostgreSQL => $this->applyPgsqlTls($config, $ssl),
            default => $config,
        };
    }

    private function applyMysqlTls(array $config, SslConfig $ssl): array
    {
        if ($ssl->mode === SslMode::Disable) {
            return $config;
        }

        $options = [
            Mysql::ATTR_SSL_CA => $ssl->mode === SslMode::Verify && $ssl->ca !== null ? $this->certificates->resolve($ssl->ca) : '',
            Mysql::ATTR_SSL_VERIFY_SERVER_CERT => $ssl->mode === SslMode::Verify,
        ];

        if ($ssl->cert !== null && $ssl->key !== null) {
            $options[Mysql::ATTR_SSL_CERT] = $this->certificates->resolve($ssl->cert);
            $options[Mysql::ATTR_SSL_KEY] = $this->certificates->resolve($ssl->key);
        }

        $config['options'] = $options;

        return $config;
    }

    private function applyPgsqlTls(array $config, SslConfig $ssl): array
    {
        $config['sslmode'] = match ($ssl->mode) {
            SslMode::Disable => 'disable',
            SslMode::Require => 'require',
            SslMode::Verify => 'verify-full',
        };

        if ($ssl->mode === SslMode::Verify) {

            $config['sslrootcert'] = $ssl->ca !== null ? $this->quoteLibpqPath($this->certificates->resolve($ssl->ca)) : 'system';
        }

        if ($ssl->mode !== SslMode::Disable && $ssl->cert !== null && $ssl->key !== null) {
            $config['sslcert'] = $this->quoteLibpqPath($this->certificates->resolve($ssl->cert));
            $config['sslkey'] = $this->quoteLibpqPath($this->certificates->resolve($ssl->key));
        }

        return $config;
    }

    private function quoteLibpqPath(string $path): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $path)."'";
    }
}
