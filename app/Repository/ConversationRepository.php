<?php

namespace App\Repository;

use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;

class ConversationRepository
{
    public function __construct(
        #[CurrentUser]
        public User $user
    ) {}

    public function get(Project $project): Collection
    {
        return $this->user->conversations()->where('project_id', $project->id)->get();
    }
}
