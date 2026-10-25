<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

final readonly class RunResultData
{
    public function __construct(
        public bool $success,
        public array $tables,
        public int $totalRows,
        public int $skippedRows,
        public float $durationSeconds,
        public ?string $failureReason,
    ) {}
}
