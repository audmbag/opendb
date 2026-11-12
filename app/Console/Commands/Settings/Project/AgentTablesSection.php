<?php

namespace App\Console\Commands\Settings\Project;

use App\Console\Commands\Settings\ProjectSection;
use App\Http\Requests\Project\ProjectAgentTablesRequest;
use App\Models\Project;
use App\Repository\ProjectRepository;
use Exception;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

/**
 * The tables of the database the agent of the project may see.
 */
class AgentTablesSection extends ProjectSection
{
    public function __construct(protected ProjectRepository $projects) {}

    public function label(): string
    {
        return __('projects.settings.nav.agent_tables');
    }

    public function run(Project $project): void
    {
        try {
            $tables = $project->driver_config->tables_info;
        } catch (Exception $exception) {
            error(__('projects.messages.tables_unreadable', ['error' => $exception->getMessage()]));

            return;
        }

        if ($tables === []) {
            warning(__('console.project_settings.no_tables'));

            return;
        }

        $names = array_column($tables, 'name');

        $accessible = multiselect(
            __('projects.settings.agent_tables.title'),
            array_combine($names, $names),
            default: array_column(array_filter($tables, fn (array $table): bool => $table['agentAccessEnabled']), 'name'),
            scroll: 15,
            hint: __('projects.settings.agent_tables.description'),
        );

        $request = $this->validate(ProjectAgentTablesRequest::class, [
            'agentDisabled' => array_values(array_diff($names, array_map(strval(...), $accessible))),
        ]);

        if ($request === null) {
            return;
        }

        $this->projects->updateAgentTables($project, $request->validated('agentDisabled'));

        info(__('projects.messages.agent_tables_saved'));
    }
}
