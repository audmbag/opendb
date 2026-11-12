<?php

namespace App\Console\Commands\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

use function Laravel\Prompts\select;

/**
 * Asks on which project the user may open the command runs.
 */
trait ChoosesProject
{
    /**
     * The project given by its id or its name, or else chosen among the projects the user may open. Null once the reason
     * is reported: the user has no project, or none like the given one.
     */
    protected function chooseProject(User $user, ?string $given): ?Project
    {
        $projects = Project::query()->accessibleBy($user)->clearDrafts();

        if ($given !== null) {
            $project = $projects->where(fn (Builder $query) => $query->whereKey($given)->orWhere('name', $given))->first();

            if ($project === null) {
                $this->error(__('console.project_not_found', ['project' => $given]));
            }

            return $project;
        }

        $names = $projects->latest()->pluck('name', 'id');

        if ($names->isEmpty()) {
            $this->error(__('console.no_project'));

            return null;
        }

        return Project::query()->accessibleBy($user)->findOrFail(select(__('console.project'), $names->all(), scroll: 10));
    }
}
