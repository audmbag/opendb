<?php

namespace App\Policies;

use App\Enums\PrivilegeEnum;
use App\Models\DriverConfig;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;

class DriverConfigPolicy
{
    public function __construct(private PrivilegeManager $privileges) {}

    /**
     * Only who manages the project of a connection may change it or read its credentials.
     */
    public function update(User $user, DriverConfig $config): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::ManageProject, $config->project_id);
    }
}
