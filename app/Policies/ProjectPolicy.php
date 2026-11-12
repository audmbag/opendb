<?php

namespace App\Policies;

use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;

/**
 * What a user may do with a project, after the privileges granted to them or to their roles; the root may do everything.
 */
class ProjectPolicy
{
    public function __construct(private PrivilegeManager $privileges) {}

    /**
     * A user may open a project once they hold a privilege on it, on one of its tables, or on every project.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->privileges->for($user)->hasAnyOn($project);
    }

    public function create(User $user): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::CreateProject);
    }

    /**
     * Change the settings, the connection or the memory of the project, or delete it.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::ManageProject, $project);
    }

    /**
     * Let the agent change the database, which needs a privilege that changes it, on the project or one of its tables.
     */
    public function writeWithAgent(User $user, Project $project): bool
    {
        return $this->privileges->for($user)->tablePrivileges($project)->writes();
    }

    public function clone(User $user, Project $project): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::Clone, $project);
    }

    public function import(User $user, Project $project): bool
    {
        return $this->privileges->for($user)->allows(PrivilegeEnum::Import, $project);
    }

    /**
     * Grant or revoke privileges on the project or on its tables.
     */
    public function share(User $user, Project $project): bool
    {
        return $this->privileges->for($user)->canGrantAny($project);
    }
}
