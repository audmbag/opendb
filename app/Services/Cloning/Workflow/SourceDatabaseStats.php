<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Database\Contracts\DriverContract;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Statistiques de la base source qui dimensionnent le clonage : plages des clés primaires et taille des lignes.
 */
class SourceDatabaseStats
{
    /**
     * Identifiant maximal et nombre de lignes des tables à clé primaire sur une colonne, pour CloningSourceConfig.
     * Les noms sont échappés à la main : la grammaire de Laravel lirait « 1.5e3 » comme « 1 ».« 5e3 ».
     *
     * @param  array<int, array{name: string, primary_key?: list<string>}>  $schema  Retour de BaseDriver::getSchema().
     * @return array<string, array{max: ?string, count: int}>
     */
    public function primaryKeyRanges(DriverContract $driver, array $schema): array
    {
        $connection = $driver->getConnection();
        $quote = $this->quoter($connection);
        $ranges = [];

        foreach (array_filter($schema, fn (array $table): bool => count($table['primary_key'] ?? []) === 1) as $table) {
            $row = (array) $connection->selectOne("select max({$quote((string) $table['primary_key'][0])}) as max_id, count(*) as row_count from {$quote($table['name'])}");

            $ranges[$table['name']] = [
                'max' => $row['max_id'] !== null ? (string) $row['max_id'] : null,
                'count' => (int) $row['row_count'],
            ];
        }

        return $ranges;
    }

    /**
     * Taille moyenne d'une ligne de la table la plus lourde, d'après les statistiques de la base (null si indisponibles).
     *
     * @param  array<string, array{max: ?string, count: int}>  $primaryKeyRanges
     */
    public function largestRowBytes(DriverContract $driver, array $primaryKeyRanges): ?int
    {
        $connection = $driver->getConnection();
        $sql = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => 'select max(data_length / greatest(table_rows, 1)) as row_bytes from information_schema.tables where table_schema = database()',
            'pgsql' => "select max(pg_total_relation_size(c.oid) / greatest(c.reltuples, 1)) as row_bytes from pg_class c join pg_namespace n on n.oid = c.relnamespace where c.relkind = 'r' and n.nspname = current_schema()",
            'sqlite' => $this->sqliteRowBytesQuery($connection, $primaryKeyRanges),
            default => null,
        };

        if ($sql === null) {
            return null;
        }

        try {
            $rowBytes = ((array) $connection->selectOne($sql))['row_bytes'] ?? null;
        } catch (QueryException) {
            return null;
        }

        return $rowBytes !== null ? (int) ceil((float) $rowBytes) : null;
    }

    /**
     * Taille des tables d'après la table virtuelle dbstat de SQLite, absente de certaines compilations (la requête
     * échoue alors) ; les lignes sont comptées sur les tables à clé primaire déjà lues par primaryKeyRanges().
     *
     * @param  array<string, array{max: ?string, count: int}>  $primaryKeyRanges
     */
    protected function sqliteRowBytesQuery(Connection $connection, array $primaryKeyRanges): ?string
    {
        $counts = array_filter(array_map(fn (array $range): int => $range['count'], $primaryKeyRanges));

        if ($counts === []) {
            return null;
        }

        $pdo = $connection->getPdo();
        $rows = $counts
            |> array_keys(...)
            |> (fn (array $tables): array => array_map(fn (string|int $table, int $count): string => 'select '.$pdo->quote((string) $table).' as name, '.$count.' as row_count', $tables, $counts))
            |> (fn (array $selects): string => implode(' union all ', $selects));

        return "select max(s.bytes / c.row_count) as row_bytes from (select name, sum(pgsize) as bytes from dbstat group by name) s join ({$rows}) c on c.name = s.name";
    }

    /**
     * @return callable(string): string
     */
    protected function quoter(Connection $connection): callable
    {
        return in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            ? fn (string $name): string => '`'.str_replace('`', '``', $name).'`'
            : fn (string $name): string => '"'.str_replace('"', '""', $name).'"';
    }
}
