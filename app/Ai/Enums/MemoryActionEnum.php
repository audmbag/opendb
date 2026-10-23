<?php

namespace App\Ai\Enums;

enum MemoryActionEnum: string
{
    case Add = 'add';

    case Replace = 'replace';

    case Remove = 'remove';

    public function requiresNote(): bool
    {
        return $this !== self::Remove;
    }

    public function requiresMatch(): bool
    {
        return $this !== self::Add;
    }
}
