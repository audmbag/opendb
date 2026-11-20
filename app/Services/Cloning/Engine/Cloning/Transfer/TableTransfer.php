<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Audit\CloningAuditLog;
use App\Services\Cloning\Engine\Cloning\SkippedRow;
use App\Services\Cloning\Engine\Data\Cloning\TableCloningConfigData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;
use App\Services\Cloning\Engine\Exceptions\CloningCancelledException;
use PDO;
use Throwable;

/**
 * Copie les lignes d'une table de la source vers la cible : par la base elle-même quand elle le peut, sinon lot par
 * lot à travers PHP, où elles sont anonymisées et remappées.
 */
final readonly class TableTransfer
{
    /**
     * @param  ?int  $expectedRows  Lignes à copier, null si elles n'ont pas pu être comptées.
     * @param  list<string>  $primaryKey  Colonnes de la clé primaire, notées pour chaque ligne refusée.
     * @param  callable(int): void  $afterChunk  Reçoit les lignes lues jusqu'ici ; peut arrêter le clonage.
     */
    public function __construct(
        private CloningAuditLog $log,
        private TableCloningConfigData $config,
        private TableSchemaData $schema,
        private PDO $source,
        private DatabaseConnectionType $sourceType,
        private PDO $target,
        private DatabaseConnectionType $targetType,
        private RowTransformer $transformer,
        private ?SameServerCopier $copier,
        private int $chunkSize,
        private ?int $expectedRows,
        private array $primaryKey,
        private mixed $afterChunk,
    ) {}

    /**
     * @return array{int, int, bool, ?string, list<SkippedRow>} Lignes copiées, lignes refusées, échec, raison de l'échec, lignes refusées
     */
    public function run(): array
    {
        $key = new TableKeyResolver($this->source, $this->sourceType)->resolve($this->schema);
        $after = null;
        $copied = 0;

        if ($this->copier instanceof SameServerCopier && $this->config->rows->strategy === 'full' && ! $this->transformer->changesRows()) {
            $direct = $this->copier->copy($this->schema, $key, $this->expectedRows, $this->afterChunk);

            if ($direct['completed']) {
                return [$direct['rows'], 0, false, null, []];
            }

            $this->log->info('direct_copy_failed', ['table' => $this->config->tableName, 'rows' => $direct['rows'], 'error' => $direct['error']]);
            [$after, $copied] = [$direct['after'], $direct['rows']];
        }

        return $this->throughPhp($key, $after, $copied);
    }

    /**
     * @param  list<mixed>|null  $after
     * @return array{int, int, bool, ?string, list<SkippedRow>}
     */
    private function throughPhp(?TableKey $key, ?array $after, int $rows): array
    {
        $reader = new TableRowReader($this->source, $this->sourceType, $this->config->tableName, $this->config->rows, $key, $this->chunkSize);
        $writer = new TableRowWriter($this->target, $this->targetType, $this->config->tableName, ValueAdapter::binaryColumns($this->schema));
        $values = ValueAdapter::for($this->schema, $this->sourceType, $this->targetType);
        $read = 0;
        $skippedRows = [];
        $firstInsertError = null;

        try {
            foreach ($reader->chunks($after) as $chunk) {
                $result = $writer->insert($this->transformer->apply($values->apply($chunk)));
                $rows += $result->inserted;
                $firstInsertError ??= $result->bulkError;

                foreach ($result->rowErrors as $index => $error) {
                    $skippedRows[] = $this->skipped($read, $index, $chunk[$index], $error);
                }

                $read += count($chunk);
                ($this->afterChunk)($rows + count($skippedRows));
            }
        } catch (CloningCancelledException $cancelled) {
            throw $cancelled;
        } catch (Throwable $throwable) {
            return [$rows, count($skippedRows), true, $throwable->getMessage(), $skippedRows];
        } finally {
            $writer->finish();
        }

        if ($rows === 0 && $skippedRows !== []) {
            $reason = sprintf('All %d rows failed to insert', count($skippedRows)).($firstInsertError !== null ? sprintf(': %s', $firstInsertError) : '');

            return [0, count($skippedRows), true, $reason, $skippedRows];
        }

        return [$rows, count($skippedRows), false, null, $skippedRows];
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     */
    private function skipped(int $chunkOffset, int $index, array $sourceRow, string $error): SkippedRow
    {
        $snapshot = array_intersect_key($sourceRow, array_flip($this->primaryKey));
        $skipped = new SkippedRow(
            tableName: $this->config->tableName,
            chunkOffset: $chunkOffset,
            rowIndex: $index,
            pkSnapshot: $snapshot === [] ? null : $snapshot,
            sqlError: $error,
        );

        $this->log->warning('row_skipped', [
            'table' => $skipped->tableName,
            'chunk_offset' => $skipped->chunkOffset,
            'row_index' => $skipped->rowIndex,
            'pk' => $skipped->pkSnapshot,
            'error' => $skipped->sqlError,
        ]);

        return $skipped;
    }
}
