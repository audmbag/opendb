<?php

namespace App\Console\Commands\Concerns;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Http\Requests\Users\GrantRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\Exception\GrantException;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Database\Eloquent\Builder;
use ValueError;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * The commands granting and revoking privileges: who receives them, on which level, and which ones. What is not given
 * as an option is asked, and the request of the interface checks the answers.
 */
trait ChoosesPrivileges
{
    use ManagesAccounts;

    /**
     * Grant or revoke the privileges the options or the answers describe.
     *
     * @param  callable(User, User|Role, list<PrivilegeEnum>, ?Project, ?string): void  $change
     */
    protected function changePrivileges(callable $change, string $message): int
    {
        $actor = $this->logIn();

        if ($actor === null) {
            return self::FAILURE;
        }

        $grantee = $this->option('role') ? $this->chooseRole($this->argument('grantee')) : $this->chooseAccount($this->argument('grantee'), __('users.fields.user'));

        if ($grantee === null) {
            return self::FAILURE;
        }

        [$project, $table] = $this->chooseLevel($actor);

        if ($project === false) {
            return self::FAILURE;
        }

        $level = match (true) {
            $project === null => PrivilegeLevelEnum::Global,
            $table === null => PrivilegeLevelEnum::Project,
            default => PrivilegeLevelEnum::Table,
        };

        $privileges = $this->choosePrivileges($level);

        if ($privileges === null) {
            return self::FAILURE;
        }

        $request = $this->validated(GrantRequest::class, [
            'grantee_type' => $grantee instanceof Role ? 'role' : 'user',
            'grantee_id' => $grantee->getKey(),
            'privileges' => array_map(fn (PrivilegeEnum $privilege): string => $privilege->value, $privileges),
            'project_id' => $project?->getKey(),
            'table' => $table,
            'grant_option' => $this->hasOption('with-grant-option') && $this->option('with-grant-option'),
        ]);

        if ($request === null) {
            return self::FAILURE;
        }

        try {
            $change($actor, $grantee, $request->privileges(), $request->project(), $request->table());
        } catch (GrantException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($message);
        $this->showGrants($grantee);

        return self::SUCCESS;
    }

    protected function showGrants(User|Role $grantee): void
    {
        foreach (app(PrivilegeManager::class)->showGrants($grantee) as $statement) {
            $this->line($statement);
        }
    }

    /**
     * The project and the table of the options, or else asked. The project is false once the reason it cannot be used is reported.
     *
     * @return array{0: Project|false|null, 1: ?string}
     */
    private function chooseLevel(User $actor): array
    {
        $given = $this->option('project');
        $table = $this->option('table');

        if ($given === null && $table !== null) {
            $this->error(__('privileges.errors.table_without_project'));

            return [false, null];
        }

        if ($given === null && $this->option('privilege') !== []) {
            return [null, null];
        }

        if ($given === null) {
            $level = PrivilegeLevelEnum::from(select(__('users.fields.level'), array_combine(
                array_map(fn (PrivilegeLevelEnum $level): string => $level->value, PrivilegeLevelEnum::cases()),
                array_map(fn (PrivilegeLevelEnum $level): string => $level->label(), PrivilegeLevelEnum::cases()),
            )));

            if ($level === PrivilegeLevelEnum::Global) {
                return [null, null];
            }

            $projects = Project::query()->accessibleBy($actor)->clearDrafts()->orderBy('name')->pluck('name', 'id')->all();

            if ($projects === []) {
                $this->error(__('console.no_project'));

                return [false, null];
            }

            $project = Project::query()->findOrFail(select(__('console.project'), $projects, scroll: 10));

            return [$project, $level === PrivilegeLevelEnum::Table ? select(__('users.fields.table'), $project->driver_config->driverFor($actor)->listTables(false), scroll: 10) : null];
        }

        $project = Project::query()->where(fn (Builder $query) => $query->whereKey($given)->orWhere('name', $given))->first();

        if ($project === null) {
            $this->error(__('console.project_not_found', ['project' => $given]));

            return [false, null];
        }

        return [$project, $table];
    }

    /**
     * The privileges of the options, `all` standing for every privilege of the level, or else chosen among them. Null
     * once an unknown name is reported.
     *
     * @return list<PrivilegeEnum>|null
     */
    private function choosePrivileges(PrivilegeLevelEnum $level): ?array
    {
        $names = $this->option('privilege');

        if ($names === []) {
            $choices = PrivilegeEnum::forLevel($level);

            return array_map(PrivilegeEnum::from(...), array_values(multiselect(
                __('users.fields.privileges'),
                array_combine(
                    array_map(fn (PrivilegeEnum $privilege): string => $privilege->value, $choices),
                    array_map(fn (PrivilegeEnum $privilege): string => "{$privilege->keyword()} · {$privilege->label()}", $choices),
                ),
                required: true,
                scroll: 12,
            )));
        }

        $privileges = [];

        foreach (array_merge(...array_map(fn (string $name): array => explode(',', $name), $names)) as $name) {
            if (in_array(strtolower(trim($name)), ['all', 'all privileges'], true)) {
                $privileges = [...$privileges, ...PrivilegeEnum::forLevel($level)];

                continue;
            }

            try {
                $privileges[] = PrivilegeEnum::fromKeyword($name);
            } catch (ValueError) {
                $this->error(__('users.errors.unknown_privilege', ['name' => trim($name), 'privileges' => implode(', ', array_map(fn (PrivilegeEnum $privilege): string => $privilege->value, PrivilegeEnum::cases()))]));

                return null;
            }
        }

        return array_values(array_unique($privileges, SORT_REGULAR));
    }
}
