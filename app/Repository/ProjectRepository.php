<?php

namespace App\Repository;

use App\Enums\ProjectStatusEnum;
use App\Models\DriverConfig;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemory;
use DB;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;

readonly class ProjectRepository
{
    public function __construct(
        #[CurrentUser]
        protected User $user
    ) {}

    public function storeInfo(mixed $data): Project
    {
        if ($data['id'] ?? false) {
            return $this->update(Project::findOrFail($data['id']), $data);
        }

        return $this->user->projects()->create($data);
    }

    public function storeDriverConfig(Project $project, array $data): DriverConfig
    {
        /**
         * @var DriverConfig $config
         */
        $config = null;

        if ($data['id'] ?? false) {
            $config = tap($project->driver_config)->update($data);
        } else {
            $config = $project->driver_config()->create($data);
        }

        return $config;
    }

    public function saveTableInfos(Project $project, array $data): Project
    {
        DB::transaction(function () use ($project, $data) {
            $project->driver_config->update([
                'disabled_tables' => $data['agentDisabled'] ?? [],
            ]);

            $project->update(['status' => ProjectStatusEnum::ACTIVE]);
        });

        return $project;
    }

    public function update(Project $project, array $data): Project
    {
        return tap($project)->update($data);
    }

    public function delete(Project $project): Project
    {
        return tap($project, function (Project $project) {
            $project->delete();

            new ProjectMemory($project)->delete();
        });
    }

    public function getRecentProjects(): Collection
    {
        return Project::clearDrafts()
            ->with('driver_config.driver')
            ->latest()
            ->limit(4)
            ->get();
    }

    public function getProjectCount(): int
    {
        return Project::clearDrafts()->count();
    }

    public function switchDriverConfig(Project $project, mixed $cloningDriverConfig, mixed $agentDisabled): DriverConfig
    {
        $driverConfig = $project->driver_config;

        DB::transaction(function () use ($project, $cloningDriverConfig, $agentDisabled, $driverConfig) {

            $driverConfig->update([
                ...$cloningDriverConfig,
                'ssh' => $cloningDriverConfig['ssh'] ?? null,
                'disabled_tables' => $agentDisabled,
            ]);

            $project->update(['status' => ProjectStatusEnum::ACTIVE]);
        });

        return $driverConfig;
    }
}
