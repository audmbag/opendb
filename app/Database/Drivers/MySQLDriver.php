<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use App\Database\ExplainOutput;
use App\Database\ReadOnlyQueryGuard;
use App\Database\Schema\Identifier;
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
        $this->assertQueryAllowed($sql);

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

    public function estimatedRowCount(string $table): ?int
    {
        $row = $this->connection->selectOne(
            'SELECT TABLE_ROWS AS estimate FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );

        return isset($row->estimate) ? (int) $row->estimate : null;
    }

    public function showCreateTable(string $table): string
    {
        $row = (array) $this->connection->selectOne('SHOW CREATE TABLE '.Identifier::quote($this->connection, $table));

        return ($row['Create Table'] ?? $row['Create View'] ?? '').';';
    }

    public function explain(string $sql, array $bindings = []): ExplainOutput
    {
        $this->assertQueryAllowed($sql);

        return $this->inRolledBackReadOnlyTransaction(fn (Connection $connection): ExplainOutput => new ExplainOutput(
            raw: json_decode((string) array_first((array) $connection->select("EXPLAIN FORMAT=JSON {$sql}", $bindings)[0]), true),
            classic: ExplainOutput::table($connection->select("{$this->traditionalExplain()} {$sql}", $bindings)),
            version: (string) $connection->getServerVersion(),
        ));
    }

    /**
     * Le mot-clé du tableau EXPLAIN classique, que MySQL n'affiche plus par défaut quand explain_format vaut TREE.
     */
    protected function traditionalExplain(): string
    {
        return 'EXPLAIN FORMAT=TRADITIONAL';
    }

    public function connectionId(): ?string
    {
        return (string) $this->connection->selectOne('SELECT CONNECTION_ID() AS id')->id;
    }

    /**
     * KILL QUERY interrompt la requête sans fermer la connexion, qui renvoie alors son erreur à l'exécution en cours.
     */
    public function cancel(string $connectionId): bool
    {
        return $this->connection->statement('KILL QUERY '.(int) $connectionId);
    }

    /**
     * Le DDL est validé sans attendre la fin de la transaction, et une table MyISAM ne revient pas en arrière :
     * l'annulation n'est garantie que pour des lectures et des écritures sur des tables InnoDB.
     *
     * @param  list<string>  $statements
     */
    public function canRollBack(array $statements): bool
    {
        $writes = array_values(array_filter($statements, fn (string $statement): bool => ! ReadOnlyQueryGuard::isReadOnly($statement)));

        if ($writes === []) {
            return true;
        }

        foreach ($writes as $statement) {
            if (preg_match('/^\s*(?:CREATE|ALTER|DROP|RENAME|TRUNCATE|GRANT|REVOKE|LOCK|UNLOCK)\b/i', $statement)) {
                return false;
            }
        }

        [$tableEngines, $otherSchemas] = $this->tableEngines();

        return collect($writes)->every(fn (string $statement): bool => self::writeTouchesOnlyInnoDbTables($statement, $tableEngines, $otherSchemas));
    }

    /**
     * Une table MyISAM ne revient pas en arrière au ROLLBACK : l'essai n'est fait que si toutes les tables citées sont InnoDB.
     */
    public function dryRunAffectedRows(string $sql, array $bindings = []): ?int
    {
        [$tableEngines, $otherSchemas] = $this->tableEngines();

        return self::writeTouchesOnlyInnoDbTables($sql, $tableEngines, $otherSchemas) ? parent::dryRunAffectedRows($sql, $bindings) : null;
    }

    /**
     * Le moteur de chaque table de la base, par nom, et les autres bases du serveur.
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    protected function tableEngines(): array
    {
        $tableEngines = collect($this->connection->select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        ))->mapWithKeys(fn (object $table): array => [(string) $table->name => (string) $table->engine])->all();

        $otherSchemas = array_map(
            fn (object $schema): string => (string) $schema->name,
            $this->connection->select('SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME <> DATABASE()'),
        );

        return [$tableEngines, $otherSchemas];
    }

    /**
     * Indique si toutes les tables qu'une écriture peut toucher sont InnoDB. Tout identifiant qui porte le nom d'une table compte,
     * même s'il s'agit d'une colonne ou d'un alias : mieux vaut refuser l'essai que d'oublier une table. Citer une autre base le refuse aussi.
     *
     * @param  array<string, string>  $tableEngines  le moteur de chaque table de la base, par nom
     * @param  array<int, string>  $otherSchemas
     */
    public static function writeTouchesOnlyInnoDbTables(string $sql, array $tableEngines, array $otherSchemas): bool
    {
        preg_match_all('/`((?:[^`]|``)+)`|[\p{L}\p{N}_$]+/u', $sql, $matches, PREG_SET_ORDER);

        $identifiers = array_map(
            fn (array $match): string => mb_strtolower(isset($match[1]) ? str_replace('``', '`', $match[1]) : $match[0]),
            $matches
        );

        if (array_intersect($identifiers, array_map(mb_strtolower(...), $otherSchemas))) {
            return false;
        }

        $citedEngines = array_filter(
            $tableEngines,
            fn (string $table): bool => in_array(mb_strtolower($table), $identifiers, true),
            ARRAY_FILTER_USE_KEY
        );

        return $citedEngines !== [] && collect($citedEngines)->every(fn (string $engine): bool => strcasecmp($engine, 'InnoDB') === 0);
    }
}
