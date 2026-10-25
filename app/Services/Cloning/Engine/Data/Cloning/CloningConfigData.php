<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

final readonly class CloningConfigData
{
    public function __construct(
        public string $version,
        public string $connectionName,
        public CloningOptionsData $options,
        public array $tables,
        public ?KeyRemappingConfigData $keyRemapping = null,
        public array $skipTables = [],
    ) {}

    public function getTable(string $name): ?TableCloningConfigData
    {
        foreach ($this->tables as $table) {
            if ($table->tableName === $name) {
                return $table;
            }
        }

        return null;
    }
}
