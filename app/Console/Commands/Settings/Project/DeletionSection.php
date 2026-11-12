<?php

namespace App\Console\Commands\Settings\Project;

use App\Console\Commands\Settings\ProjectSection;
use App\Http\Requests\Project\ProjectDeletionRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;

use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * The deletion of the project, confirmed by typing its name.
 */
class DeletionSection extends ProjectSection
{
    public function __construct(protected ProjectRepository $projects) {}

    public function label(): string
    {
        return __('projects.delete.title');
    }

    public function run(Project $project): void
    {
        warning(__('projects.settings.danger_zone.delete_description'));

        $request = $this->validate(ProjectDeletionRequest::class, [
            'name' => text(__('projects.delete.name_label'), hint: __('console.project_settings.type_name', ['name' => $project->name])),
        ], ['project' => $project]);

        if ($request === null) {
            return;
        }

        $this->projects->delete($project);

        info(__('projects.deleted', ['project' => $project->name]));
    }
}
