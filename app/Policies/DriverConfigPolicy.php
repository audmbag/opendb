<?php

namespace App\Policies;

use App\Models\DriverConfig;
use App\Models\User;

class DriverConfigPolicy
{
    /**
     * Only the owner of the project of a connection may change it or read its credentials.
     */
    public function update(User $user, DriverConfig $config): bool
    {
        return $config->project()->whereBelongsTo($user)->exists();
    }
}
