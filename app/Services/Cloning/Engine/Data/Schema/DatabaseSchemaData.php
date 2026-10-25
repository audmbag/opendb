<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

final readonly class DatabaseSchemaData
{
    public function __construct(
        public string $databaseName,
        public array $tables,
    ) {}

    public function getTable(string $name): ?TableSchemaData
    {
        foreach ($this->tables as $table) {
            if ($table->name === $name) {
                return $table;
            }
        }

        return null;
    }

    public function hasTable(string $name): bool
    {
        return $this->getTable($name) instanceof TableSchemaData;
    }
}
