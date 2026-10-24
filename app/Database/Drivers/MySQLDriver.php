<?php

namespace App\Database\Drivers;

use App\Database\Contracts\DriverContract;
use Illuminate\Database\Connection;
use Throwable;

class MySQLDriver extends BaseDriver implements DriverContract
{
    /**
     * Les étapes du plan JSON de MySQL et MariaDB affichées dans l'arbre, avec leur libellé.
     */
    private const array PLAN_STEPS = [
        'query_block' => 'Requête',
        'nested_loop' => 'Boucle imbriquée (jointure)',
        'ordering_operation' => 'Tri',
        'filesort' => 'Tri',
        'grouping_operation' => 'Regroupement',
        'duplicates_removal' => 'Suppression des doublons',
        'temporary_table' => 'Table temporaire',
        'windowing' => 'Fenêtrage',
        'union_result' => 'Union',
        'materialized_from_subquery' => 'Sous-requête matérialisée',
        'attached_subqueries' => 'Sous-requêtes',
        'optimized_away_subqueries' => 'Sous-requêtes optimisées',
    ];

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

    public function explain(string $sql, array $bindings = []): array
    {
        $row = $this->executeReadOnly("EXPLAIN FORMAT=JSON {$sql}", $bindings)[0];

        return $this->explainSteps(json_decode((string) array_values((array) $row)[0], true));
    }

    /**
     * Les étapes contenues dans une partie du plan JSON, les clés inconnues étant traversées sans créer d'étape.
     *
     * @param  array<string|int, mixed>  $plan
     * @return list<array{label: string, detail: ?string, cost: ?float, rows: ?int, warning: ?string, children: list<array<string, mixed>>}>
     */
    protected function explainSteps(array $plan): array
    {
        $steps = [];

        foreach ($plan as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $children = array_is_list($value)
                ? collect($value)->filter(fn (mixed $item): bool => is_array($item))->flatMap(fn (array $item): array => $this->explainSteps($item))->values()->all()
                : $this->explainSteps($value);

            if ($key === 'table' && ! array_is_list($value)) {
                $steps[] = $this->planNode(
                    label: 'Table '.($value['table_name'] ?? '?'),
                    detail: collect([
                        isset($value['access_type']) ? "accès {$value['access_type']}" : null,
                        isset($value['key']) ? "index {$value['key']}" : null,
                        $value['attached_condition'] ?? null,
                    ])->filter()->implode(' · '),
                    cost: $value['cost_info']['prefix_cost'] ?? null,
                    rows: $value['rows_examined_per_scan'] ?? $value['rows'] ?? null,
                    warning: ($value['access_type'] ?? null) === 'ALL' ? 'Parcours complet de la table' : null,
                    children: $children,
                );

                continue;
            }

            if (! is_string($key) || ! isset(self::PLAN_STEPS[$key])) {
                array_push($steps, ...$children);

                continue;
            }

            $steps[] = $this->planNode(
                label: self::PLAN_STEPS[$key],
                cost: $value['cost_info']['query_cost'] ?? $value['cost_info']['sort_cost'] ?? null,
                warning: match (true) {
                    ($value['using_filesort'] ?? false) === true => 'Tri sans index (filesort)',
                    ($value['using_temporary_table'] ?? false) === true => 'Table temporaire',
                    default => null,
                },
                children: $children,
            );
        }

        return $steps;
    }

    /**
     * Une table MyISAM ne revient pas en arrière au ROLLBACK : l'essai n'est fait que si toutes les tables citées sont InnoDB.
     */
    public function dryRunAffectedRows(string $sql, array $bindings = []): ?int
    {
        $tableEngines = collect($this->connection->select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        ))->mapWithKeys(fn (object $table): array => [(string) $table->name => (string) $table->engine])->all();

        $otherSchemas = array_map(
            fn (object $schema): string => (string) $schema->name,
            $this->connection->select('SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME <> DATABASE()'),
        );

        return self::writeTouchesOnlyInnoDbTables($sql, $tableEngines, $otherSchemas) ? parent::dryRunAffectedRows($sql, $bindings) : null;
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
