<?php

namespace App\Database\Exceptions;

use App\Enums\PrivilegeEnum;
use RuntimeException;

/**
 * A query or an operation the privileges of the user do not allow, explained like MySQL does.
 */
class PrivilegeException extends RuntimeException
{
    public static function denied(PrivilegeEnum $privilege, string $user, string $table): self
    {
        return new self(__('privileges.errors.denied', ['privilege' => $privilege->keyword(), 'user' => $user, 'table' => $table]));
    }

    public static function deniedOnProject(PrivilegeEnum $privilege, string $user): self
    {
        return new self(__('privileges.errors.denied_project', ['privilege' => $privilege->keyword(), 'user' => $user]));
    }

    public static function statement(): self
    {
        return new self(__('privileges.errors.statement'));
    }

    public static function severalTargets(PrivilegeEnum $privilege): self
    {
        return new self(__('privileges.errors.several_targets', ['privilege' => $privilege->keyword()]));
    }

    public static function nestedWrite(): self
    {
        return new self(__('privileges.errors.nested_write'));
    }

    public static function systemCatalog(): self
    {
        return new self(__('privileges.errors.system_catalog'));
    }

    public static function dynamicSql(): self
    {
        return new self(__('privileges.errors.dynamic_sql'));
    }

    public static function otherDatabase(string $name): self
    {
        return new self(__('privileges.errors.other_database', ['name' => $name]));
    }
}
