<?php

namespace App\Services\Privileges;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Grant;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * The privileges as the interface edits them: one line by privilege, with the project, the table and the grantee named.
 */
class GrantReader
{
    public function __construct(private PrivilegeResolver $resolver) {}

    /**
     * The privileges of the grantee, or only those on the project and its tables when one is given.
     *
     * @return list<array{privilege: string, project_id: ?string, project_name: ?string, table_name: ?string, grant_option: bool}>
     */
    public function of(User|Role $grantee, ?Project $project = null): array
    {
        return $grantee->grants()->when($project, fn ($query) => $query->whereBelongsTo($project))->with('project:id,name')->get()
            ->map(fn (Grant $grant): array => [
                'privilege' => $grant->privilege->value,
                'project_id' => $grant->project_id,
                'project_name' => $grant->project?->name,
                'table_name' => $grant->table_name,
                'grant_option' => $grant->grant_option,
            ])
            ->values()
            ->all();
    }

    /**
     * Every privilege granted on the project or on one of its tables, by user and role.
     *
     * @return list<array{grantee_type: string, grantee_id: int, grantee_name: string, privilege: string, table_name: ?string, grant_option: bool}>
     */
    public function on(Project $project): array
    {
        return Grant::query()
            ->whereBelongsTo($project)
            ->with('grantee:id,name')
            ->get()
            ->filter(fn (Grant $grant): bool => $grant->grantee !== null)
            ->map(fn (Grant $grant): array => [
                'grantee_type' => $grant->grantee instanceof Role ? 'role' : 'user',
                'grantee_id' => (int) $grant->grantee_id,
                'grantee_name' => $grant->grantee->name,
                'privilege' => $grant->privilege->value,
                'table_name' => $grant->table_name,
                'grant_option' => $grant->grant_option,
            ])
            ->values()
            ->all();
    }

    /**
     * What the interface needs to offer privileges: each privilege with its levels, and the projects the user may grant on.
     *
     * @return array{privileges: list<array{value: string, keyword: string, label: string, description: string, levels: list<string>}>, projects: list<array{id: string, name: string}>}
     */
    public function catalog(User $actor): array
    {
        $privileges = $this->resolver->for($actor);

        return [
            'privileges' => array_map(fn (PrivilegeEnum $privilege): array => [
                'value' => $privilege->value,
                'keyword' => $privilege->keyword(),
                'label' => $privilege->label(),
                'description' => $privilege->description(),
                'levels' => array_map(fn (PrivilegeLevelEnum $level): string => $level->value, $privilege->levels()),
            ], PrivilegeEnum::cases()),
            'projects' => Project::query()
                ->clearDrafts()
                ->accessibleBy($actor)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn (Project $project): bool => $privileges->canGrantAny($project))
                ->map(fn (Project $project): array => ['id' => (string) $project->id, 'name' => $project->name])
                ->values()
                ->all(),
        ];
    }
}
