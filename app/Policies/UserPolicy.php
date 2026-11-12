<?php

namespace App\Policies;

use App\Enums\PrivilegeEnum;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;

/**
 * The users are managed with the CREATE USER privilege, like in MySQL; the root account is only changed by the root.
 */
class UserPolicy
{
    public function __construct(private PrivilegeManager $privileges) {}

    public function viewAny(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function update(User $user, User $account): bool
    {
        return ! $account->is_root && $this->managesUsers($user);
    }

    /**
     * Nobody deletes the root, nor their own account, which would lock them out.
     */
    public function delete(User $user, User $account): bool
    {
        return ! $account->is_root && ! $account->is($user) && $this->managesUsers($user);
    }

    private function managesUsers(User $user): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::CreateUser);
    }
}
