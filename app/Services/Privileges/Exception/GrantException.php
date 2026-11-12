<?php

namespace App\Services\Privileges\Exception;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use RuntimeException;

/**
 * A grant or a revocation OpenDB refuses, with a message the user can read.
 */
class GrantException extends RuntimeException
{
    public static function invalidLevel(PrivilegeEnum $privilege, PrivilegeLevelEnum $level): self
    {
        return new self(__('privileges.errors.invalid_level', ['privilege' => $privilege->keyword(), 'level' => $level->label()]));
    }

    public static function notGrantable(PrivilegeEnum $privilege): self
    {
        return new self(__('privileges.errors.not_grantable', ['privilege' => $privilege->keyword()]));
    }

    public static function root(): self
    {
        return new self(__('privileges.errors.root'));
    }

    public static function tableWithoutProject(): self
    {
        return new self(__('privileges.errors.table_without_project'));
    }
}
