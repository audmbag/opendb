<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

final readonly class SkippedRow
{
    public function __construct(
        public string $tableName,
        public int $chunkOffset,
        public int $rowIndex,
        public ?array $pkSnapshot,
        public string $sqlError,
    ) {}
}
