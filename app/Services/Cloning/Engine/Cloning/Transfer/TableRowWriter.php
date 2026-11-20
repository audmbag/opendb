<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Insère les lignes d'une table de la cible par lots.
 *
 * Chaque requête `INSERT … VALUES (…), (…)` est préparée une fois par taille de lot, sous la limite de paramètres de
 * la base. Les lots sont validés ensemble, par transactions de COMMIT_EVERY_ROWS lignes : sans transaction, la base
 * écrirait sur le disque à chaque lot. Un lot refusé est repris ligne à ligne, pour ne perdre que les lignes fautives.
 */
final class TableRowWriter
{
    /**
     * Lignes validées ensemble ; un clonage annulé garde les lignes déjà insérées.
     */
    const int COMMIT_EVERY_ROWS = 50_000;

    private readonly string $table;

    /**
     * @var list<string>|null
     */
    private ?array $columns = null;

    /**
     * @var array<int, PDOStatement>
     */
    private array $statements = [];

    private ?int $rowsPerStatement = null;

    private int $uncommittedRows = 0;

    /**
     * @var array<string, true>
     */
    private readonly array $binaryColumns;

    /**
     * @param  list<string>  $binaryColumns  Colonnes envoyées comme binaires (ValueAdapter::binaryColumns()).
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly DatabaseConnectionType $type,
        string $table,
        array $binaryColumns = [],
    ) {
        $this->table = SqlIdentifier::quote($table, $type);
        $this->binaryColumns = array_fill_keys($binaryColumns, true);
    }

    /**
     * @param  list<array<string, mixed>>  $rows  Lignes de mêmes colonnes, dans le même ordre.
     */
    public function insert(array $rows): ChunkWriteResult
    {
        if ($rows === []) {
            return new ChunkWriteResult(0);
        }

        $this->columns ??= array_map(strval(...), array_keys($rows[0]));
        $this->rowsPerStatement ??= $this->rowsPerStatement();
        $this->begin();

        $inserted = 0;
        $bulkError = null;
        $rowErrors = [];

        try {
            // Chaque requête est reprise seule : celles déjà passées ne doivent pas être insérées deux fois.
            foreach (array_chunk($rows, $this->rowsPerStatement, preserve_keys: true) as $slice) {
                try {
                    $this->guarded(fn () => $this->execute($slice));
                    $inserted += count($slice);
                } catch (Throwable $throwable) {
                    $bulkError ??= $throwable->getMessage();
                    [$sliceInserted, $sliceErrors] = $this->insertOneByOne($slice);
                    $inserted += $sliceInserted;
                    $rowErrors += $sliceErrors;
                }
            }
        } finally {
            $this->commitIfDue(count($rows));
        }

        return new ChunkWriteResult($inserted, $bulkError, $rowErrors);
    }

    /**
     * Valide les lignes en attente : à la fin de la table, ou quand le clonage s'arrête.
     */
    public function finish(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }

        $this->uncommittedRows = 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  Lignes par rang dans le lot.
     * @return array{int, array<int, string>} Lignes insérées, et erreur de chaque ligne refusée par rang
     */
    private function insertOneByOne(array $rows): array
    {
        $inserted = 0;
        $rowErrors = [];

        foreach ($rows as $index => $row) {
            try {
                $this->guarded(fn () => $this->execute([$row]));
                $inserted++;
            } catch (Throwable $rowError) {
                $rowErrors[$index] = $rowError->getMessage();
            }
        }

        return [$inserted, $rowErrors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function execute(array $rows): void
    {
        $statement = $this->statements[count($rows)] ??= $this->pdo->prepare($this->insertSql(count($rows)));
        $position = 1;

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                // Comme Connection::bindValues() de Laravel, après prepareBindings() : un booléen devient un entier. Un
                // flottant est écrit en entier, et non sur les 14 chiffres du réglage « precision » de PHP.
                match (true) {
                    $value === null => $statement->bindValue($position++, null, PDO::PARAM_NULL),
                    is_int($value) => $statement->bindValue($position++, $value, PDO::PARAM_INT),
                    is_bool($value) => $statement->bindValue($position++, (int) $value, PDO::PARAM_INT),
                    is_float($value) => $statement->bindValue($position++, var_export($value, true)),
                    is_resource($value), isset($this->binaryColumns[$column]) => $statement->bindValue($position++, $value, PDO::PARAM_LOB),
                    default => $statement->bindValue($position++, $value),
                };
            }
        }

        $statement->execute();
    }

    /**
     * Sous PostgreSQL, une requête refusée annule toute la transaction : elle passe par un point de sauvegarde.
     */
    private function guarded(callable $insert): void
    {
        if ($this->type !== DatabaseConnectionType::PostgreSQL) {
            $insert();

            return;
        }

        $this->pdo->exec('SAVEPOINT opendb_clone_rows');

        try {
            $insert();
        } catch (Throwable $throwable) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT opendb_clone_rows');

            throw $throwable;
        }

        $this->pdo->exec('RELEASE SAVEPOINT opendb_clone_rows');
    }

    private function insertSql(int $rowCount): string
    {
        $values = '('.implode(', ', array_fill(0, count($this->columns), '?')).')';

        return "INSERT INTO {$this->table} (".SqlIdentifier::list($this->columns, $this->type).') VALUES '.implode(', ', array_fill(0, $rowCount, $values));
    }

    /**
     * Lignes par requête, sous la limite de paramètres liés de la base.
     */
    private function rowsPerStatement(): int
    {
        $maxParameters = match ($this->type) {
            DatabaseConnectionType::Sqlite => version_compare((string) $this->pdo->query('SELECT sqlite_version()')->fetchColumn(), '3.32.0', '>=') ? 32_766 : 999,
            default => 65_535,
        };

        return max(1, intdiv($maxParameters, max(1, count($this->columns))));
    }

    private function begin(): void
    {
        if (! $this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    private function commitIfDue(int $rows): void
    {
        $this->uncommittedRows += $rows;

        if ($this->uncommittedRows >= self::COMMIT_EVERY_ROWS) {
            $this->finish();
        }
    }
}
