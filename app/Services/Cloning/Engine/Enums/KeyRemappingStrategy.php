<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Enums;

enum KeyRemappingStrategy: string
{
    case RandomInteger = 'random_integer';
    case NewUuid = 'new_uuid';

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
