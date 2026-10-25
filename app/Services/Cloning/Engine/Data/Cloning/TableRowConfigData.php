<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

use App\Services\Cloning\Engine\Enums\ClearMode;

final readonly class TableRowConfigData
{
    public function __construct(
        public string $strategy,
        public ?int $limit,
        public ?string $sortBy,
        public ClearMode $clear = ClearMode::None,
    ) {}
}
