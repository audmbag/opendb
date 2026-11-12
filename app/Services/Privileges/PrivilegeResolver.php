<?php

namespace App\Services\Privileges;

use App\Models\Grant;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\Data\PrivilegeSet;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gathers the privileges of a user and of their roles in one query, once per request or command.
 */
class PrivilegeResolver
{
    /**
     * @var array<int|string, PrivilegeSet>
     */
    private array $resolved = [];

    public function for(User $user): PrivilegeSet
    {
        return $this->resolved[$user->getKey()] ??= $user->is_root
            ? PrivilegeSet::root()
            : PrivilegeSet::fromGrants($this->grantsOf($user));
    }

    /**
     * Forget what was gathered, once privileges changed.
     */
    public function flush(): void
    {
        $this->resolved = [];
    }

    /**
     * @return iterable<Grant>
     */
    private function grantsOf(User $user): iterable
    {
        return Grant::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('grantee_type', $user->getMorphClass())->where('grantee_id', $user->getKey()))
                ->orWhere(fn (Builder $query) => $query
                    ->where('grantee_type', (new Role)->getMorphClass())
                    ->whereIn('grantee_id', $user->roles()->select('roles.id'))))
            ->get(['privilege', 'project_id', 'table_name', 'grant_option']);
    }
}
