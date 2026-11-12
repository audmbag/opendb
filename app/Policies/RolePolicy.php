<?php

namespace App\Policies;

use App\Enums\PrivilegeEnum;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;

/**
 * The roles are managed with the CREATE USER privilege, like CREATE ROLE in MySQL.
 */
class RolePolicy
{
    public function __construct(private PrivilegeManager $privileges) {}

    public function manage(User $user): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::CreateUser);
    }
}
