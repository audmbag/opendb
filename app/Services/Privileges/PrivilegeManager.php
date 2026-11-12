<?php

namespace App\Services\Privileges;

use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\Data\PrivilegeSet;
use App\Services\Privileges\Exception\GrantException;

/**
 * Gateway between the application and the privileges of its users, after those of MySQL.
 */
final readonly class PrivilegeManager
{
    public function __construct(
        private PrivilegeResolver $resolver,
        private GrantWriter $writer,
        private GrantFormatter $formatter,
        private GrantReader $reader,
    ) {}

    /**
     * Every privilege of the user, theirs and those of their roles.
     */
    public function for(User $user): PrivilegeSet
    {
        return $this->resolver->for($user);
    }

    /**
     * @param  list<PrivilegeEnum>  $privileges
     *
     * @throws GrantException when the actor may not grant one of the privileges, on this level or to this grantee.
     */
    public function grant(?User $actor, User|Role $grantee, array $privileges, ?Project $project = null, ?string $table = null, bool $grantOption = false): void
    {
        $this->writer->grant($actor, $grantee, $privileges, $project, $table, $grantOption);
    }

    /**
     * @param  list<PrivilegeEnum>  $privileges
     *
     * @throws GrantException when the actor may not revoke one of the privileges, on this level or from this grantee.
     */
    public function revoke(?User $actor, User|Role $grantee, array $privileges, ?Project $project = null, ?string $table = null, bool $grantOptionOnly = false): void
    {
        $this->writer->revoke($actor, $grantee, $privileges, $project, $table, $grantOptionOnly);
    }

    public function grantOwnership(Project $project, User $owner): void
    {
        $this->writer->grantOwnership($project, $owner);
    }

    public function renameTable(Project $project, string $from, string $to): void
    {
        $this->writer->renameTable($project, $from, $to);
    }

    public function forgetTable(Project $project, string $table): void
    {
        $this->writer->forgetTable($project, $table);
    }

    /**
     * The privileges of the user or role, written like SHOW GRANTS does, only those on the project when one is given.
     *
     * @return list<string>
     */
    public function showGrants(User|Role $grantee, ?Project $project = null): array
    {
        return $this->formatter->statements($grantee, $project);
    }

    /**
     * @return list<array{privilege: string, project_id: ?string, project_name: ?string, table_name: ?string, grant_option: bool}>
     */
    public function grantsOf(User|Role $grantee, ?Project $project = null): array
    {
        return $this->reader->of($grantee, $project);
    }

    /**
     * @return list<array{grantee_type: string, grantee_id: int, grantee_name: string, privilege: string, table_name: ?string, grant_option: bool}>
     */
    public function grantsOn(Project $project): array
    {
        return $this->reader->on($project);
    }

    /**
     * @return array{privileges: list<array{value: string, keyword: string, label: string, description: string, levels: list<string>}>, projects: list<array{id: string, name: string}>}
     */
    public function catalog(User $actor): array
    {
        return $this->reader->catalog($actor);
    }
}
