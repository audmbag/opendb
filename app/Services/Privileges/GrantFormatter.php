<?php

namespace App\Services\Privileges;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Grant;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * Writes the privileges of a user or a role as the GRANT statements SHOW GRANTS gives in MySQL.
 */
class GrantFormatter
{
    /**
     * The GRANT statements of the grantee, or only those on the project and its tables when one is given.
     *
     * @return list<string>
     */
    public function statements(User|Role $grantee, ?Project $project = null): array
    {
        $name = $this->quote($grantee->name, "'");

        if ($grantee instanceof User && $grantee->is_root) {
            return ["GRANT ALL PRIVILEGES ON *.* TO {$name} WITH GRANT OPTION"];
        }

        $grants = $grantee->grants()->when($project, fn ($query) => $query->whereBelongsTo($project))->with('project:id,name')->get();
        $statements = $grants
            ->groupBy(fn (Grant $grant): string => implode('|', [$grant->project_id, $grant->table_name, (int) $grant->grant_option]))
            ->sortBy(fn ($group): string => sprintf('%d|%s|%s', $this->rank($group->first()->level()), $group->first()->project?->name, $group->first()->table_name))
            ->map(fn ($group): string => sprintf(
                'GRANT %s ON %s TO %s%s',
                $this->privileges($group->pluck('privilege')->all(), $group->first()->level()),
                $this->target($group->first()->project, $group->first()->table_name),
                $name,
                $group->first()->grant_option ? ' WITH GRANT OPTION' : '',
            ))
            ->values()
            ->all();

        if ($grantee instanceof User && $project === null) {
            $statements = [
                ...$statements,
                ...$grantee->roles()->orderBy('name')->pluck('name')->map(fn (string $role): string => 'GRANT '.$this->quote($role, "'")." TO {$name}")->all(),
            ];
        }

        return $statements === [] ? ["GRANT USAGE ON *.* TO {$name}"] : $statements;
    }

    /**
     * @param  list<PrivilegeEnum>  $privileges
     */
    private function privileges(array $privileges, PrivilegeLevelEnum $level): string
    {
        $all = PrivilegeEnum::forLevel($level);

        if (array_all($all, fn (PrivilegeEnum $privilege): bool => in_array($privilege, $privileges, true))) {
            return 'ALL PRIVILEGES';
        }

        return array_filter($all, fn (PrivilegeEnum $privilege): bool => in_array($privilege, $privileges, true))
            |> (fn (array $held): array => array_map(fn (PrivilegeEnum $privilege): string => $privilege->keyword(), $held))
            |> (fn (array $keywords): string => implode(', ', $keywords));
    }

    private function target(?Project $project, ?string $table): string
    {
        return match (true) {
            $project === null => '*.*',
            $table === null => $this->quote($project->name, '`').'.*',
            default => $this->quote($project->name, '`').'.'.$this->quote($table, '`'),
        };
    }

    private function rank(PrivilegeLevelEnum $level): int
    {
        return array_search($level, PrivilegeLevelEnum::cases(), true);
    }

    private function quote(string $name, string $quote): string
    {
        return $quote.str_replace($quote, $quote.$quote, $name).$quote;
    }
}
