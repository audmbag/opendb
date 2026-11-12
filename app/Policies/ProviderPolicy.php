<?php

namespace App\Policies;

use App\Enums\PrivilegeEnum;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;

/**
 * The AI providers and their API keys are shared by every user: only who holds MANAGE PROVIDERS may change them.
 */
class ProviderPolicy
{
    public function __construct(private PrivilegeManager $privileges) {}

    public function manage(User $user): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::ManageProviders);
    }
}
