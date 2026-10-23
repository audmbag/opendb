<?php

namespace App\Database;

use App\Database\Concerns\QuotesIdentifiers;
use App\Database\Contracts\DriverContract;
use RuntimeException;

/**
 * A read-only SELECT written by the agent, wrapped in a subquery to be counted, sorted and paginated without being rewritten.
 */
class SelectQuery
{
    use QuotesIdentifiers;

    public readonly string $sql;

    /**
     * @param  array<string|int, mixed>  $bindings
     *
     * @throws RuntimeException when the query is not a single SELECT statement.
     */
    public function __construct(
        protected DriverContract $driver,
        string $sql,
        protected array $bindings = [],
    ) {
        $this->sql = ReadOnlyQueryGuard::assertSelect($sql);
    }

    public function count(): int
    {
        $rows = $this->driver->executeReadOnly("SELECT COUNT(*) AS aggregate FROM ({$this->sql}) AS opendb_query", $this->bindings);

        return (int) ($rows[0]->aggregate ?? 0);
    }

    /**
     * The names of the columns returned by the query, empty when it returns no row.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return array_keys($this->rows(1)[0] ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException when the sort column is not one of the columns of the query.
     */
    public function rows(int $limit, int $offset = 0, ?string $sortColumn = null, string $direction = 'asc'): array
    {
        $sql = "SELECT * FROM ({$this->sql}) AS opendb_query";

        if ($sortColumn !== null) {
            if (! in_array($sortColumn, $this->columns(), true)) {
                throw new RuntimeException("La colonne de tri [{$sortColumn}] n'existe pas dans le résultat de la requête.");
            }

            $sql .= ' ORDER BY '.$this->quoteIdentifier($sortColumn)
                .(strtolower($direction) === 'desc' ? ' DESC' : ' ASC');
        }

        $sql .= ' LIMIT '.max(0, $limit).' OFFSET '.max(0, $offset);

        return array_map(
            fn (object $row): array => array_map(static::displayableValue(...), (array) $row),
            $this->driver->executeReadOnly($sql, $this->bindings),
        );
    }

    /**
     * A value that can be encoded in JSON: binary strings are replaced by a short description.
     */
    public static function displayableValue(mixed $value): mixed
    {
        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            return '[binaire, '.strlen($value).' octets]';
        }

        return $value;
    }
}
