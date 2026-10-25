<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Enums;

enum SslMode: string
{
    case Require = 'require';
    case Verify = 'verify';
    case Disable = 'disable';

    public function label(): string
    {
        return match ($this) {
            self::Require => 'Require (encrypted, not verified)',
            self::Verify => 'Verify (encrypted + certificate check)',
            self::Disable => 'Disable (plaintext)',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
