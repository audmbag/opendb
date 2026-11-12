<?php

namespace App\Services\Privileges\Data;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Grant;
use App\Models\Project;

/**
 * Every privilege a user holds, theirs and those of their roles: a privilege on the whole instance reaches every project,
 * and a privilege on a project every one of its tables, as in MySQL.
 */
final readonly class PrivilegeSet
{
    /**
     * @param  array<string, bool>  $global  Whether each privilege held on the whole instance may be granted, by privilege.
     * @param  array<string, array<string, bool>>  $projects  The same, by project.
     * @param  array<string, array<string, array<string, bool>>>  $tables  The same, by project and table named in lower case.
     */
    private function __construct(
        private bool $root,
        private array $global = [],
        private array $projects = [],
        private array $tables = [],
    ) {}

    public static function root(): self
    {
        return new self(true);
    }

    /**
     * @param  iterable<Grant>  $grants
     */
    public static function fromGrants(iterable $grants): self
    {
        $global = $projects = $tables = [];

        foreach ($grants as $grant) {
            $privilege = $grant->privilege->value;

            match ($grant->level()) {
                PrivilegeLevelEnum::Global => $global[$privilege] = ($global[$privilege] ?? false) || $grant->grant_option,
                PrivilegeLevelEnum::Project => $projects[$grant->project_id][$privilege] = ($projects[$grant->project_id][$privilege] ?? false) || $grant->grant_option,
                PrivilegeLevelEnum::Table => $tables[$grant->project_id][mb_strtolower($grant->table_name)][$privilege] = ($tables[$grant->project_id][mb_strtolower($grant->table_name)][$privilege] ?? false) || $grant->grant_option,
            };
        }

        return new self(false, $global, $projects, $tables);
    }

    public function isRoot(): bool
    {
        return $this->root;
    }

    /**
     * Whether the user holds the privilege on the level given: the whole instance without a project, a project without a table, or a table.
     */
    public function allows(PrivilegeEnum $privilege, Project|string|null $project = null, ?string $table = null): bool
    {
        return $this->holds($privilege, $project, $table, grantable: false);
    }

    /**
     * Whether the user may grant the privilege on the level given to someone else: they must hold it there, or on a level above,
     * with the grant option.
     */
    public function canGrant(PrivilegeEnum $privilege, Project|string|null $project = null, ?string $table = null): bool
    {
        return $this->holds($privilege, $project, $table, grantable: true);
    }

    /**
     * Whether the user may grant something on the level given.
     */
    public function canGrantAny(Project|string|null $project = null, ?string $table = null): bool
    {
        return array_any(PrivilegeEnum::cases(), fn (PrivilegeEnum $privilege): bool => $this->canGrant($privilege, $project, $table));
    }

    /**
     * Whether the user may open the project at all: they hold a privilege on it, on one of its tables, or on every project.
     */
    public function hasAnyOn(Project|string $project): bool
    {
        $projectId = $this->projectId($project);

        return $this->reachesEveryProject() || ($this->projects[$projectId] ?? []) !== [] || ($this->tables[$projectId] ?? []) !== [];
    }

    /**
     * The projects the user may open, or null when they may open every one.
     *
     * @return list<string>|null
     */
    public function projectIds(): ?array
    {
        if ($this->reachesEveryProject()) {
            return null;
        }

        return array_values(array_unique(array_map('strval', [...array_keys($this->projects), ...array_keys($this->tables)])));
    }

    /**
     * The privileges the user holds on the whole instance.
     *
     * @return list<PrivilegeEnum>
     */
    public function globalPrivileges(): array
    {
        if ($this->root) {
            return PrivilegeEnum::cases();
        }

        return array_map(PrivilegeEnum::from(...), array_keys($this->global));
    }

    /**
     * What the user may do with the tables of the project.
     */
    public function tablePrivileges(Project|string $project): TablePrivileges
    {
        if ($this->root) {
            return TablePrivileges::unrestricted();
        }

        $projectId = $this->projectId($project);
        $onProject = array_keys([...$this->onLevel(PrivilegeLevelEnum::Project, $this->global), ...($this->projects[$projectId] ?? [])]);

        return new TablePrivileges(
            array_map(PrivilegeEnum::from(...), $onProject),
            array_map(fn (array $privileges): array => array_map(PrivilegeEnum::from(...), array_keys($privileges)), $this->tables[$projectId] ?? []),
        );
    }

    private function holds(PrivilegeEnum $privilege, Project|string|null $project, ?string $table, bool $grantable): bool
    {
        if ($this->root) {
            return true;
        }

        $value = $privilege->value;
        $projectId = $project === null ? null : $this->projectId($project);
        $matches = fn (array $privileges): bool => array_key_exists($value, $privileges) && (! $grantable || $privileges[$value]);

        return $matches($this->global)
            || ($projectId !== null && $matches($this->projects[$projectId] ?? []))
            || ($projectId !== null && $table !== null && $matches($this->tables[$projectId][mb_strtolower($table)] ?? []));
    }

    /**
     * Whether a privilege on the whole instance opens every project, unlike the administration of OpenDB alone.
     */
    private function reachesEveryProject(): bool
    {
        return $this->root || $this->onLevel(PrivilegeLevelEnum::Project, $this->global) !== [];
    }

    /**
     * @param  array<string, bool>  $privileges
     * @return array<string, bool>
     */
    private function onLevel(PrivilegeLevelEnum $level, array $privileges): array
    {
        return array_filter($privileges, fn (string $privilege): bool => PrivilegeEnum::from($privilege)->allowsLevel($level), ARRAY_FILTER_USE_KEY);
    }

    private function projectId(Project|string $project): string
    {
        return $project instanceof Project ? (string) $project->getKey() : $project;
    }
}
