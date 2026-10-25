<?php

namespace App\Ai\Enums;

enum MailRecipientActionEnum: string
{
    case List = 'list';

    case Add = 'add';

    case Remove = 'remove';

    public function requiresEmail(): bool
    {
        return $this !== self::List;
    }
}
