<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Data\Cloning;

final readonly class ColumnCloningConfigData
{
    public function __construct(
        public string $columnName,
        public string $strategy,
        public ?string $fakerMethod,
        public array $fakerArguments,
        public ?string $hashAlgorithm,
        public ?string $hashSalt,
        public ?string $maskChar,
        public ?int $visibleChars,
        public ?bool $preserveFormat,
        public ?string $staticValue,
        public ?string $template = null,
        public ?string $remappingUse = null,
        public ?array $remappingForeignKeys = null,
    ) {}
}
