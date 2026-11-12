<?php

namespace App\Repository;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The roles of OpenDB, named sets of privileges given to users, like the roles of MySQL 8.
 */
class RoleRepository
{
    /**
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        return Role::query()->withCount('users')->orderBy('name')->get();
    }

    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }

    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function update(Role $role, array $data): Role
    {
        return tap($role)->update($data);
    }

    /**
     * The users of the role lose its privileges along with it.
     */
    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function assign(Role $role, User $user): void
    {
        $role->users()->syncWithoutDetaching([$user->getKey()]);
    }

    public function unassign(Role $role, User $user): void
    {
        $role->users()->detach($user->getKey());
    }

    public function findByName(string $name): ?Role
    {
        return Role::query()->firstWhere('name', $name);
    }
}
