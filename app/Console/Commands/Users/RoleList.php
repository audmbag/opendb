<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\Role;
use App\Repository\RoleRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

#[Signature('role:list {--name= : console.options.name}')]
#[Description('console.commands.role_list.description')]
class RoleList extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(RoleRepository $roles): int
    {
        $actor = $this->logIn();

        if ($actor === null) {
            return self::FAILURE;
        }

        if ($actor->cannot('manage', Role::class)) {
            $this->error(__('users.errors.denied'));

            return self::FAILURE;
        }

        table(
            [__('users.fields.role'), __('users.fields.description'), __('users.fields.users')],
            $roles->list()->map(fn (Role $role): array => [$role->name, (string) $role->description, (string) $role->users_count])->all(),
        );

        return self::SUCCESS;
    }
}
