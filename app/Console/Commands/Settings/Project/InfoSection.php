<?php

namespace App\Console\Commands\Settings\Project;

use App\Console\Commands\Settings\ProjectSection;
use App\Http\Requests\Project\ProjectSettingsInfoRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;

use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

/**
 * The name and the description of the project, and the working directory of its agent.
 */
class InfoSection extends ProjectSection
{
    public function __construct(protected ProjectRepository $projects) {}

    public function label(): string
    {
        return __('projects.settings.nav.general');
    }

    public function run(Project $project): void
    {
        $request = $this->validate(ProjectSettingsInfoRequest::class, [
            'name' => text(__('projects.form.name'), default: $project->name, required: true),
            'description' => text(__('projects.form.description'), default: (string) $project->description, required: true),
            'path' => text(
                __('console.project_settings.path'),
                default: (string) $project->path,
                required: true,
                hint: __('projects.settings.general.path_description'),
            ),
        ], ['project' => $project]);

        if ($request === null) {
            return;
        }

        $this->projects->update($project, $request->validated());

        info(__('projects.messages.info_saved'));
    }
}
