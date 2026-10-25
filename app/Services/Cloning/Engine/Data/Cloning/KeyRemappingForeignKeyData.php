<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

final readonly class KeyRemappingForeignKeyData
{
    public function __construct(
        public string $table,
        public string $column,
        public bool $selfReferential = false,
    ) {}
}
