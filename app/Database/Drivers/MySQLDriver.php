<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use Illuminate\Database\Connection;
use Throwable;

class MySQLDriver extends BaseDriver implements DriverContract
{
    public function getConnectionString(bool $escapePassword = true): string
    {
        $config = $this->getConfig();

        $password = $escapePassword ? '[password]' : $config['password'];

        return "mysql://{$config['username']}:{$password}@{$config['host']}:{$config['port']}/{$config['database']}";
    }

    protected function databaseExists(Connection $server): bool
    {
        return $server->selectOne(
            'SELECT 1 AS found FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$this->databaseName()]
        ) !== null;
    }

    protected function canCreateDatabase(Connection $server): bool
    {
        $grants = array_map(
            fn (object $row): string => (string) array_values((array) $row)[0],
            $server->select('SHOW GRANTS FOR CURRENT_USER()')
        );

        return self::grantsAllowCreatingDatabase($grants, $this->databaseName());
    }

    /**
     * Indique si l'un des privilèges retournés par `SHOW GRANTS` permet de créer la base `$database`
     * (`CREATE` ou `ALL PRIVILEGES`, au niveau global `*.*` ou sur un schéma qui correspond à la base).
     *
     * @param  array<int, string>  $grants
     */
    public static function grantsAllowCreatingDatabase(array $grants, string $database): bool
    {
        foreach ($grants as $grant) {
            if (! preg_match('/^GRANT\s+(?<privileges>.+?)\s+ON\s+(?<target>.+?)\s+TO\s/is', $grant, $matches)) {
                continue;
            }

            $privileges = array_map(
                fn (string $privilege): string => strtoupper(trim($privilege)),
                explode(',', $matches['privileges'])
            );

            if (! array_intersect($privileges, ['ALL PRIVILEGES', 'ALL', 'CREATE'])) {
                continue;
            }

            $target = $matches['target'];

            if (! str_ends_with($target, '.*')) {
                continue;
            }

            $schema = substr($target, 0, -2);

            if ($schema === '*' || $schema === '`*`') {
                return true;
            }

            $schema = str_replace('``', '`', trim($schema, '`'));

            if (self::schemaPatternMatches($schema, $database)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare un nom de schéma d'un `GRANT` (où `%` et `_` sont des jokers, sauf s'ils sont échappés par `\`) à une base.
     */
    private static function schemaPatternMatches(string $pattern, string $database): bool
    {
        $regex = '';

        for ($i = 0, $length = strlen($pattern); $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '\\' && isset($pattern[$i + 1])) {
                $regex .= preg_quote($pattern[++$i], '/');

                continue;
            }

            $regex .= match ($char) {
                '%' => '.*',
                '_' => '.',
                default => preg_quote($char, '/'),
            };
        }

        return (bool) preg_match('/^'.$regex.'$/is', $database);
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
