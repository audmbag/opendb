<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Data\Cloning\TableRowConfigData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use Generator;
use PDO;
use Pdo\Mysql;
use PDOStatement;

/**
 * Lit les lignes d'une table de la source par lots, en tableaux associatifs.
 *
 * Une table copiée en entier est lue par tranches de sa clé, que la base trouve directement dans son index ; sans
 * clé, elle est lue d'une seule requête, que MySQL envoie au fil de l'eau et que PostgreSQL parcourt par un curseur.
 * Seules les stratégies « premières » et « dernières » lignes, bornées par leur limite, gardent `LIMIT … OFFSET`.
 */
final readonly class TableRowReader
{
    private string $table;

    public function __construct(
        private PDO $pdo,
        private DatabaseConnectionType $type,
        string $table,
        private TableRowConfigData $rows,
        private ?TableKey $key,
        private int $chunkSize,
    ) {
        $this->table = SqlIdentifier::quote($table, $type);
    }

    /**
     * @param  list<mixed>|null  $after  Valeur de la clé après laquelle reprendre la lecture.
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function chunks(?array $after = null): Generator
    {
        return match (true) {
            $this->rows->strategy !== 'full' => $this->limitedChunks(),
            $this->key instanceof TableKey => $this->keysetChunks($this->key, $after),
            $this->type === DatabaseConnectionType::PostgreSQL => $this->cursorChunks(),
            default => $this->streamedChunks(),
        };
    }

    /**
     * @param  list<mixed>|null  $after
     * @return Generator<int, list<array<string, mixed>>>
     */
    private function keysetChunks(TableKey $key, ?array $after): Generator
    {
        $columns = $key->isRowid ? "{$key->expressions[0]} AS ".TableKey::ROWID_ALIAS.', *' : '*';
        $first = $this->pdo->prepare("SELECT {$columns} FROM {$this->table} ORDER BY {$key->orderBy()} LIMIT {$this->chunkSize}");
        $next = $this->pdo->prepare("SELECT {$columns} FROM {$this->table} WHERE {$key->condition('>')} ORDER BY {$key->orderBy()} LIMIT {$this->chunkSize}");

        do {
            $statement = $after === null ? $first : $next;
            $statement->execute($after ?? []);
            $chunk = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            if ($chunk === []) {
                return;
            }

            $after = $key->valueOf(array_last($chunk));

            if ($key->isRowid) {
                // Sur place, par les index : copier le lot doublerait sa mémoire, que CloningSourceConfig budgète.
                foreach (array_keys($chunk) as $index) {
                    unset($chunk[$index][TableKey::ROWID_ALIAS]);
                }
            }

            yield $chunk;
        } while (count($chunk) === $this->chunkSize);
    }

    /**
     * Une seule requête, lue ligne à ligne : MySQL ne garde pas tout le résultat en mémoire, SQLite avance pas à pas.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    private function streamedChunks(): Generator
    {
        $isMysql = in_array($this->type, [DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB], true);

        if ($isMysql) {
            $this->pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, false);
        }

        $statement = null;

        try {
            $statement = $this->pdo->prepare("SELECT * FROM {$this->table}");
            $statement->execute();

            yield from $this->batched($statement);
        } finally {
            $statement?->closeCursor();

            if ($isMysql) {
                $this->pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, true);
            }
        }
    }

    /**
     * Sans clé, PostgreSQL ne garantit pas l'ordre de `LIMIT … OFFSET` d'une requête à l'autre : un curseur lit
     * chaque ligne une fois, sans charger toute la table.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    private function cursorChunks(): Generator
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec("DECLARE opendb_clone_rows NO SCROLL CURSOR FOR SELECT * FROM {$this->table}");
            $fetch = $this->pdo->prepare("FETCH {$this->chunkSize} FROM opendb_clone_rows");

            do {
                $fetch->execute();
                $chunk = $fetch->fetchAll(PDO::FETCH_ASSOC);

                if ($chunk !== []) {
                    yield $chunk;
                }
            } while (count($chunk) === $this->chunkSize);
        } finally {
            $this->pdo->rollBack();
        }
    }

    /**
     * @return Generator<int, list<array<string, mixed>>>
     */
    private function limitedChunks(): Generator
    {
        $limit = $this->rows->limit ?? PHP_INT_MAX;
        $sortBy = SqlIdentifier::quote($this->rows->sortBy ?? 'id', $this->type);
        $direction = $this->rows->strategy === 'last' ? 'DESC' : 'ASC';
        $offset = 0;

        while ($offset < $limit) {
            $size = min($this->chunkSize, $limit - $offset);
            $statement = $this->pdo->prepare("SELECT * FROM {$this->table} ORDER BY {$sortBy} {$direction} LIMIT {$size} OFFSET {$offset}");
            $statement->execute();
            $chunk = $statement->fetchAll(PDO::FETCH_ASSOC);

            if ($chunk === []) {
                return;
            }

            yield $chunk;

            if (count($chunk) < $size) {
                return;
            }

            $offset += $size;
        }
    }

    /**
     * @return Generator<int, list<array<string, mixed>>>
     */
    private function batched(PDOStatement $statement): Generator
    {
        $chunk = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $chunk[] = $row;

            if (count($chunk) === $this->chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }
}
