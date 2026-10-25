<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

use App\Services\Cloning\Engine\Enums\KeyRemappingStrategy;

final readonly class KeyRemappingTableData
{
    public function __construct(
        public string $table,
        public string $primaryKey,
        public KeyRemappingStrategy $strategy,
        public array $foreignKeys,
    ) {}
}
