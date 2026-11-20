<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Database\DatabaseConnectionService;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use PDO;
use Throwable;

/**
 * Copie une table conservée telle quelle par `INSERT … SELECT`, sans que les lignes passent par PHP, quand la cible
 * peut lire la source : deux fichiers SQLite, dont la source est rattachée à la cible (ATTACH), ou deux bases d'un
 * même serveur MySQL ou MariaDB ouvertes avec le même compte.
 */
final class SameServerCopier
{
    /**
     * Lignes copiées par requête : la progression avance et l'annulation est vue entre deux tranches.
     */
    const int BATCH_ROWS = 50_000;

    /**
     * Jusqu'à ce nombre de lignes, SQLite copie la table d'une seule requête : sans `WHERE`, il recopie les lignes
     * sans les décoder, quatre fois plus vite qu'en tranches (≈ 1 s pour 500 000 lignes), sans suivi pendant ce temps.
     */
    const int SQLITE_SINGLE_STATEMENT_ROWS = 1_000_000;

    private const string ATTACHED_SCHEMA = 'opendb_clone_source';

    private function __construct(
        private readonly PDO $target,
        private readonly DatabaseConnectionType $type,
        private readonly string $sourceSchema,
    ) {}

    /**
     * Null si la cible ne peut pas lire la source : les lignes passent alors par PHP.
     */
    public static function between(ConnectionData $source, ConnectionData $target, PDO $targetPdo, DatabaseConnectionService $connector): ?self
    {
        if ($source->type !== $target->type) {
            return null;
        }

        try {
            return match ($source->type) {
                DatabaseConnectionType::Sqlite => self::attachedSqlite($source, $target, $targetPdo),
                DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => self::sameMysqlServer($source, $target, $targetPdo, $connector),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private static function attachedSqlite(ConnectionData $source, ConnectionData $target, PDO $targetPdo): ?self
    {
        $sourceFile = $source->database !== null ? realpath($source->database) : false;

        if ($sourceFile === false || $sourceFile === ($target->database !== null ? realpath($target->database) : false)) {
            return null;
        }

        $targetPdo->prepare('ATTACH DATABASE ? AS '.self::ATTACHED_SCHEMA)->execute([$sourceFile]);

        return new self($targetPdo, $source->type, self::ATTACHED_SCHEMA);
    }

    /**
     * Le même compte sur le même serveur lit forcément la base source ; deux tunnels SSH ont deux ports différents.
     */
    private static function sameMysqlServer(ConnectionData $source, ConnectionData $target, PDO $targetPdo, DatabaseConnectionService $connector): ?self
    {
        $sameServer = $source->database !== null
            && $connector->resolvedHost($source) === $connector->resolvedHost($target)
            && $source->port === $target->port
            && $source->username === $target->username
            && $source->ssl == $target->ssl
            && $connector->resolvePassword($source) === $connector->resolvePassword($target);

        if (! $sameServer) {
            return null;
        }

        // En lecture cohérente, INSERT … SELECT ne verrouille pas les lignes de la source.
        $targetPdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');

        return new self($targetPdo, $source->type, SqlIdentifier::quote($source->database, $source->type));
    }

    /**
     * @param  ?int  $expectedRows  Lignes de la table, null si elles n'ont pas pu être comptées.
     * @param  callable(int): void  $afterBatch  Reçoit les lignes copiées jusqu'ici ; peut arrêter le clonage.
     * @return array{rows: int, completed: bool, after: list<mixed>|null, error: ?string} `after` : clé de la
     *                                                                                    dernière ligne copiée, d'où reprendre par PHP si une tranche a échoué
     */
    public function copy(TableSchemaData $table, ?TableKey $key, ?int $expectedRows, callable $afterBatch): array
    {
        if ($this->type === DatabaseConnectionType::Sqlite && $expectedRows !== null && $expectedRows <= self::SQLITE_SINGLE_STATEMENT_ROWS) {
            $key = null;
        }

        $columns = SqlIdentifier::list(array_map(fn (ColumnSchemaData $column): string => $column->name, $table->columns), $this->type);
        $source = $this->sourceSchema.'.'.SqlIdentifier::quote($table->name, $this->type);
        $insert = 'INSERT INTO '.SqlIdentifier::quote($table->name, $this->type)." ({$columns}) SELECT {$columns} FROM {$source}";

        $rows = 0;
        $after = null;

        do {
            try {
                $boundary = $key instanceof TableKey ? $this->boundary($source, $key, $after) : null;
                $conditions = array_filter([
                    $after !== null ? $key->condition('>') : null,
                    $boundary !== null ? $key->condition('<=') : null,
                ]);

                $statement = $this->target->prepare($insert.($conditions === [] ? '' : ' WHERE '.implode(' AND ', $conditions)));
                $statement->execute([...$after ?? [], ...$boundary ?? []]);
                $rows += $statement->rowCount();
            } catch (Throwable $throwable) {
                return ['rows' => $rows, 'completed' => false, 'after' => $after, 'error' => $throwable->getMessage()];
            }

            $afterBatch($rows);
            $after = $boundary;
        } while ($boundary !== null);

        return ['rows' => $rows, 'completed' => true, 'after' => null, 'error' => null];
    }

    /**
     * Clé de la dernière ligne de la prochaine tranche, null si les lignes restantes tiennent dans une seule.
     *
     * @param  list<mixed>|null  $after
     * @return list<mixed>|null
     */
    private function boundary(string $source, TableKey $key, ?array $after): ?array
    {
        $statement = $this->target->prepare(sprintf(
            'SELECT %s FROM %s%s ORDER BY %s LIMIT 1 OFFSET %d',
            $key->orderBy(),
            $source,
            $after !== null ? ' WHERE '.$key->condition('>') : '',
            $key->orderBy(),
            self::BATCH_ROWS - 1,
        ));
        $statement->execute($after ?? []);
        $boundary = $statement->fetch(PDO::FETCH_NUM);
        $statement->closeCursor();

        return $boundary === false ? null : $boundary;
    }
}
