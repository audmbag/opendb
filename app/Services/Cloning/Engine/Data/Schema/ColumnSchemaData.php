<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

final readonly class ColumnSchemaData
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public ?string $default,
        public bool $isPrimary,
        public bool $unsigned = false,

        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
    ) {}
}
