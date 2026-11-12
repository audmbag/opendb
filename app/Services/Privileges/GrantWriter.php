<?php

namespace App\Services\Privileges;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Grant;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\Exception\GrantException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Grants and revokes privileges like GRANT and REVOKE do in MySQL: someone may only give what they hold with the grant option,
 * on the same level or on a level above, and the root keeps every privilege whatever is revoked.
 */
class GrantWriter
{
    public function __construct(private PrivilegeResolver $resolver) {}

    /**
     * Give the privileges, keeping the grant option of those already held with it.
     *
     * @param  list<PrivilegeEnum>  $privileges
     * @param  User|null  $actor  Who grants them, whose own privileges are checked, or nobody for OpenDB itself.
     *
     * @throws GrantException when a privilege may not be granted on this level, or by this actor, or to the root.
     */
    public function grant(?User $actor, User|Role $grantee, array $privileges, ?Project $project = null, ?string $table = null, bool $grantOption = false): void
    {
        $this->assertAllowed($actor, $grantee, $privileges, $project, $table);

        DB::transaction(function () use ($grantee, $privileges, $project, $table, $grantOption) {
            foreach ($privileges as $privilege) {
                $grant = $this->query($grantee, $project, $table)->where('privilege', $privilege->value)->first()
                    ?? $grantee->grants()->make(['privilege' => $privilege, 'project_id' => $project?->getKey(), 'table_name' => $table]);

                $grant->grant_option = $grant->grant_option || $grantOption;
                $grant->save();
            }
        });

        $this->resolver->flush();
    }

    /**
     * Take the privileges back, or only the right to grant them.
     *
     * @param  list<PrivilegeEnum>  $privileges
     *
     * @throws GrantException when a privilege may not be revoked on this level, or by this actor, or from the root.
     */
    public function revoke(?User $actor, User|Role $grantee, array $privileges, ?Project $project = null, ?string $table = null, bool $grantOptionOnly = false): void
    {
        $this->assertAllowed($actor, $grantee, $privileges, $project, $table);

        $grants = $this->query($grantee, $project, $table)->whereIn('privilege', array_map(fn (PrivilegeEnum $privilege): string => $privilege->value, $privileges));

        $grantOptionOnly ? $grants->update(['grant_option' => false]) : $grants->delete();

        $this->resolver->flush();
    }

    /**
     * Give every privilege on the project, with the grant option, to the user who created it.
     */
    public function grantOwnership(Project $project, User $owner): void
    {
        if (! $owner->is_root) {
            $this->grant(null, $owner, PrivilegeEnum::forLevel(PrivilegeLevelEnum::Project), $project, grantOption: true);
        }
    }

    /**
     * Keep the privileges on a table once it is renamed.
     */
    public function renameTable(Project $project, string $from, string $to): void
    {
        Grant::query()->whereBelongsTo($project)->where('table_name', $from)->update(['table_name' => $to]);

        $this->resolver->flush();
    }

    /**
     * Drop the privileges on a table once it is dropped, so that a new table of the same name does not inherit them.
     */
    public function forgetTable(Project $project, string $table): void
    {
        Grant::query()->whereBelongsTo($project)->where('table_name', $table)->delete();

        $this->resolver->flush();
    }

    /**
     * @param  list<PrivilegeEnum>  $privileges
     *
     * @throws GrantException
     */
    private function assertAllowed(?User $actor, User|Role $grantee, array $privileges, ?Project $project, ?string $table): void
    {
        if ($grantee instanceof User && $grantee->is_root) {
            throw GrantException::root();
        }

        if ($project === null && $table !== null) {
            throw GrantException::tableWithoutProject();
        }

        $level = match (true) {
            $project === null => PrivilegeLevelEnum::Global,
            $table === null => PrivilegeLevelEnum::Project,
            default => PrivilegeLevelEnum::Table,
        };

        $actorPrivileges = $actor === null ? null : $this->resolver->for($actor);

        foreach ($privileges as $privilege) {
            if (! $privilege->allowsLevel($level)) {
                throw GrantException::invalidLevel($privilege, $level);
            }

            if ($actorPrivileges !== null && ! $actorPrivileges->canGrant($privilege, $project, $table)) {
                throw GrantException::notGrantable($privilege);
            }
        }
    }

    /**
     * @return Builder<Grant>
     */
    private function query(User|Role $grantee, ?Project $project, ?string $table): Builder
    {
        return Grant::query()
            ->whereMorphedTo('grantee', $grantee)
            ->where('project_id', $project?->getKey())
            ->where('table_name', $table);
    }
}
