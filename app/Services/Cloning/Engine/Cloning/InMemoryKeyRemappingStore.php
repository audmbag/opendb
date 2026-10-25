<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

class InMemoryKeyRemappingStore implements KeyRemappingStoreInterface
{
    private array $mappings = [];

    public function storeTable(string $table, array $mappings): void
    {
        $this->mappings[$table] = $mappings;
    }

    public function lookup(string $table, string $oldValue): ?string
    {
        return $this->mappings[$table][$oldValue] ?? null;
    }

    public function cleanup(): void
    {
        $this->mappings = [];
    }
}
