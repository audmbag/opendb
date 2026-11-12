<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\ChoosesProject;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Console\Commands\Settings\Project\AgentTablesSection;
use App\Console\Commands\Settings\Project\ArchivesSection;
use App\Console\Commands\Settings\Project\ConnectionSection;
use App\Console\Commands\Settings\Project\DeletionSection;
use App\Console\Commands\Settings\Project\InfoSection;
use App\Console\Commands\Settings\Project\MemorySection;
use App\Console\Commands\Settings\ProjectSection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\select;

#[Signature('project:settings
    {project? : console.commands.project_settings.project}
    {--name= : console.options.name}')]
#[Description('console.commands.project_settings.description')]
class ProjectSettings extends Command
{
    use AuthenticatesUser, ChoosesProject, TranslatesCommand;

    /**
     * @var array<string, class-string<ProjectSection>>
     */
    private const array SECTIONS = [
        'info' => InfoSection::class,
        'connection' => ConnectionSection::class,
        'agent_tables' => AgentTablesSection::class,
        'memory' => MemorySection::class,
        'archives' => ArchivesSection::class,
        'deletion' => DeletionSection::class,
    ];

    public function handle(): int
    {
        $user = $this->authenticateUser();

        if ($user === null) {
            return self::FAILURE;
        }

        Auth::setUser($user);

        $project = $this->chooseProject($user, $this->argument('project'));

        if ($project === null) {
            return self::FAILURE;
        }

        if ($user->cannot('update', $project)) {
            $this->error(__('console.privileges.manage_denied', ['project' => $project->name]));

            return self::FAILURE;
        }

        $sections = array_map($this->laravel->make(...), self::SECTIONS);
        $menu = [...array_map(fn (ProjectSection $section): string => $section->label(), $sections), Settings::QUIT => __('console.quit')];

        while ($project->exists && ($choice = select(__('projects.settings.description', ['project' => $project->name]), $menu, scroll: 10)) !== Settings::QUIT) {
            $sections[$choice]->run($project);
        }

        return self::SUCCESS;
    }
}
