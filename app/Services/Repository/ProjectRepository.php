<?php

namespace App\Services\Repository;

use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class ProjectRepository
{
    public function __construct(
        #[CurrentUser]
        protected User $user
    ) {}

    public function create(array $projectData): Project
    {
        $project = new Project;

        DB::transaction(function () use ($projectData, &$project) {

            $project = $this->user->projects()->create($projectData);

            $project->driver_config()->create($projectData['driver_config']);
        });

        return $project;
    }

    public function update(Project $project, array $data): Project
    {
        return tap($project)->update($data);
    }

    public function delete(Project $project): Project
    {
        return tap($project)->delete();
    }

    public function getRecentProjects(): Collection
    {
        return Project::with('driver_config.driver')
            ->latest()
            ->limit(3)
            ->get();
    }

    public function getProjectCount(): int
    {
        return Project::count();
    }
}
