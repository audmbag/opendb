<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

final readonly class TableCloningConfigData
{
    public function __construct(
        public string $tableName,
        public TableRowConfigData $rows,
        public array $columns,
    ) {}

    public function getColumn(string $name): ?ColumnCloningConfigData
    {
        foreach ($this->columns as $col) {
            if ($col->columnName === $name) {
                return $col;
            }
        }

        return null;
    }
}
