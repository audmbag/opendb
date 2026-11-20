<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingConfigData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingTableData;
use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\DatabaseSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Database\DatabaseConnectionService;
use App\Services\Cloning\Engine\Enums\KeyRemappingStrategy;
use App\Services\Cloning\Engine\Exceptions\KeyRemappingExhaustedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class KeyRemappingService
{
    public function __construct(
        private readonly DatabaseConnectionService $connector,
        private readonly KeyRemappingStoreInterface $store = new InMemoryKeyRemappingStore,
        private readonly KeyTypeBoundsResolver $boundsResolver = new KeyTypeBoundsResolver,
    ) {}

    public function generateMappings(
        KeyRemappingConfigData $config,
        ConnectionData $source,
        DatabaseSchemaData $sourceSchema,
        array $orderedTables,
    ): array {
        $counts = [];

        foreach ($orderedTables as $tableName) {
            $tableConfig = $config->getTable($tableName);
            if (! $tableConfig instanceof KeyRemappingTableData) {
                continue;
            }

            $counts[$tableName] = $this->generateTableMapping($tableConfig, $source, $sourceSchema);
        }

        return $counts;
    }

    public function allocateIntegerIds(
        string $table,
        ColumnSchemaData $column,
        array $sourceIds,
        int $existingMax,
        array $existingIds,
    ): array {
        $rowCount = count($sourceIds);
        if ($rowCount === 0) {
            return [];
        }

        $typeMax = $this->boundsResolver->ceilingFor($column);
        $upperStart = max($existingMax + 1, 1);
        $upperSlots = $upperStart > $typeMax ? 0 : ($typeMax - $upperStart + 1);

        $targets = [];

        if ($upperSlots >= $rowCount) {
            for ($i = 0; $i < $rowCount; $i++) {
                $targets[] = $upperStart + $i;
            }
        } else {
            for ($i = 0; $i < $upperSlots; $i++) {
                $targets[] = $upperStart + $i;
            }

            $needed = $rowCount - $upperSlots;
            $gaps = $this->findGaps($existingIds, 1, $existingMax);

            if (count($gaps) < $needed) {
                throw new KeyRemappingExhaustedException(
                    table: $table,
                    column: $column->name,
                    columnType: $column->type,
                    unsigned: $column->unsigned,
                    typeMax: $typeMax,
                    rowCount: $rowCount,
                    upperSlots: $upperSlots,
                    gapSlots: count($gaps),
                );
            }

            for ($i = 0; $i < $needed; $i++) {
                $targets[] = $gaps[$i];
            }
        }

        $mapping = [];
        foreach ($sourceIds as $i => $oldId) {
            $mapping[$oldId] = (string) $targets[$i];
        }

        return $mapping;
    }

    private function findGaps(array $existingIds, int $lo, int $hi): array
    {
        if ($hi < $lo) {
            return [];
        }

        $set = array_flip($existingIds);
        $gaps = [];
        for ($i = $lo; $i <= $hi; $i++) {
            if (! isset($set[$i])) {
                $gaps[] = $i;
            }
        }

        return $gaps;
    }

    private function generateTableMapping(
        KeyRemappingTableData $config,
        ConnectionData $source,
        DatabaseSchemaData $sourceSchema,
    ): int {
        $connName = $this->connector->open($source);
        $pkCol = $config->primaryKey;
        $table = $config->table;

        try {
            $driver = $source->type->value;
            $quotedTable = match ($driver) {
                'mysql', 'mariadb' => '`'.$table.'`',
                default => '"'.$table.'"',
            };
            $quotedCol = match ($driver) {
                'mysql', 'mariadb' => '`'.$pkCol.'`',
                default => '"'.$pkCol.'"',
            };

            // Une seule colonne, lue brute : la table entière peut compter des millions de lignes.
            $ids = DB::connection($connName)->getPdo()->query(sprintf('SELECT %s FROM %s', $quotedCol, $quotedTable))->fetchAll(PDO::FETCH_COLUMN);

            $sourceIds = [];
            $existingIdsInt = [];
            foreach ($ids as $rawValue) {
                $oldValue = is_scalar($rawValue) ? (string) $rawValue : '';
                $sourceIds[] = $oldValue;
                if (is_numeric($oldValue)) {
                    $existingIdsInt[] = (int) $oldValue;
                }
            }

            $existingMax = $existingIdsInt === [] ? 0 : max($existingIdsInt);

            $tableMappings = match ($config->strategy) {
                KeyRemappingStrategy::NewUuid => $this->allocateUuids($sourceIds),
                KeyRemappingStrategy::RandomInteger => $this->allocateIntegerIds(
                    $table,
                    $this->resolvePkColumn($sourceSchema, $table, $pkCol),
                    $sourceIds,
                    $existingMax,
                    $existingIdsInt,
                ),
            };

            $this->store->storeTable($table, $tableMappings);

            return count($tableMappings);
        } finally {
            DB::purge($connName);
        }
    }

    private function allocateUuids(array $sourceIds): array
    {
        $mapping = [];
        foreach ($sourceIds as $oldId) {
            $mapping[$oldId] = (string) Str::uuid();
        }

        return $mapping;
    }

    private function resolvePkColumn(DatabaseSchemaData $schema, string $table, string $column): ColumnSchemaData
    {
        $tableSchema = $schema->getTable($table);
        if (! $tableSchema instanceof TableSchemaData) {
            throw new RuntimeException(sprintf("Table '%s' not found in source schema", $table));
        }

        foreach ($tableSchema->columns as $col) {
            if ($col->name === $column) {
                return $col;
            }
        }

        throw new RuntimeException(sprintf("Primary key column '%s' not found on table '%s' in source schema", $column, $table));
    }

    public function applyToRow(array $row, string $tableName, KeyRemappingConfigData $config): array
    {
        return $this->remapRow($row, $this->columnsToRemap($tableName, $config));
    }

    /**
     * Colonnes de `$tableName` à réécrire, avec la table remappée dont chacune reprend les identifiants : sa clé
     * primaire, puis ses clés étrangères vers une table remappée.
     *
     * @return list<array{column: string, table: string}>
     */
    public function columnsToRemap(string $tableName, KeyRemappingConfigData $config): array
    {
        $columns = [];
        $tableConfig = $config->getTable($tableName);

        if ($tableConfig instanceof KeyRemappingTableData) {
            $columns[] = ['column' => $tableConfig->primaryKey, 'table' => $tableName];
        }

        foreach ($config->tables as $remappedTable) {
            foreach ($remappedTable->foreignKeys as $fk) {
                if ($fk->table === $tableName) {
                    $columns[] = ['column' => $fk->column, 'table' => $remappedTable->table];
                }
            }
        }

        return $columns;
    }

    /**
     * @param  list<array{column: string, table: string}>  $columns  Retour de columnsToRemap().
     */
    public function remapRow(array $row, array $columns): array
    {
        foreach ($columns as ['column' => $column, 'table' => $table]) {
            if (array_key_exists($column, $row)) {
                $value = $row[$column];
                $row[$column] = $this->store->lookup($table, is_scalar($value) ? (string) $value : '') ?? $value;
            }
        }

        return $row;
    }

    public function cleanup(): void
    {
        $this->store->cleanup();
    }
}
