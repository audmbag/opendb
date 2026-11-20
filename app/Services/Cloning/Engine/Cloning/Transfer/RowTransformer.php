<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Cloning\AnonymizationEngine;
use App\Services\Cloning\Engine\Cloning\KeyRemappingService;
use App\Services\Cloning\Engine\Data\Cloning\ColumnCloningConfigData;
use App\Services\Cloning\Engine\Data\Cloning\KeyRemappingConfigData;
use App\Services\Cloning\Engine\Data\Cloning\TableCloningConfigData;

/**
 * Anonymise puis remappe les lignes d'une table, en ne touchant qu'aux colonnes qui changent : une table dont toutes
 * les colonnes sont conservées passe telle quelle.
 */
final readonly class RowTransformer
{
    /**
     * Stratégies qui remplacent la valeur ; « keep », « remapping » et les autres la gardent.
     */
    private const array CHANGING_STRATEGIES = ['null', 'static', 'fake', 'hash', 'mask', 'template'];

    /**
     * @param  array<string, ColumnCloningConfigData>  $anonymized  Colonnes anonymisées, par nom.
     * @param  list<array{column: string, table: string}>  $remapped  Colonnes remappées (KeyRemappingService::columnsToRemap()).
     */
    private function __construct(
        private AnonymizationEngine $engine,
        private array $anonymized,
        private ?KeyRemappingService $keyRemapping,
        private array $remapped,
    ) {}

    public static function for(
        TableCloningConfigData $table,
        AnonymizationEngine $engine,
        ?KeyRemappingService $keyRemapping,
        ?KeyRemappingConfigData $keyRemappingConfig,
    ): self {
        $anonymized = [];

        foreach ($table->columns as $column) {
            if (in_array($column->strategy, self::CHANGING_STRATEGIES, true)) {
                $anonymized[$column->columnName] ??= $column;
            }
        }

        $remapped = $keyRemapping instanceof KeyRemappingService && $keyRemappingConfig instanceof KeyRemappingConfigData
            ? $keyRemapping->columnsToRemap($table->tableName, $keyRemappingConfig)
            : [];

        return new self($engine, $anonymized, $keyRemapping, $remapped);
    }

    /**
     * Faux si les lignes sont copiées telles quelles, ce qui permet à la base de les copier elle-même.
     */
    public function changesRows(): bool
    {
        return $this->anonymized !== [] || $this->remapped !== [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function apply(array $rows): array
    {
        if (! $this->changesRows()) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            foreach ($this->anonymized as $column => $config) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->engine->transform($row[$column], $config);
                }
            }

            $rows[$index] = $this->remapped === [] ? $row : $this->keyRemapping->remapRow($row, $this->remapped);
        }

        return $rows;
    }
}
