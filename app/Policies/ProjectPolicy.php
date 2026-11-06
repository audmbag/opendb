<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Only the owner of a project may open its database.
     */
    public function view(User $user, Project $project): bool
    {
        return $project->user()->is($user);
    }

    /**
     * Only the owner of a project may change its settings or delete it.
     */
    public function update(User $user, Project $project): bool
    {
        return $project->user()->is($user);
    }
}
