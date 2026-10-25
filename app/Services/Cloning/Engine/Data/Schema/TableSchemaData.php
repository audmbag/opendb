<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Schema;

final readonly class TableSchemaData
{
    public function __construct(
        public string $name,
        public array $columns,
        public array $foreignKeys,
    ) {}
}
