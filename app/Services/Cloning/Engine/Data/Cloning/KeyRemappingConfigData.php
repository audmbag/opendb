<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

final readonly class KeyRemappingConfigData
{
    public function __construct(
        public array $tables,
    ) {}

    public function getTable(string $name): ?KeyRemappingTableData
    {
        foreach ($this->tables as $table) {
            if ($table->table === $name) {
                return $table;
            }
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->tables !== [];
    }
}
