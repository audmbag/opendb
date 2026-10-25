<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

interface KeyRemappingStoreInterface
{
    public function storeTable(string $table, array $mappings): void;

    public function lookup(string $table, string $oldValue): ?string;

    public function cleanup(): void;
}
