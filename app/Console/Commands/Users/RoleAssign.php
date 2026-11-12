<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\Role;
use App\Repository\RoleRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('role:assign
    {role? : console.commands.role_assign.role}
    {user? : console.commands.role_assign.user}
    {--revoke : console.commands.role_assign.revoke}
    {--name= : console.options.name}')]
#[Description('console.commands.role_assign.description')]
class RoleAssign extends Command
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

        $role = $this->chooseRole($this->argument('role'));
        $account = $role === null ? null : $this->chooseAccount($this->argument('user'), __('users.fields.user'));

        if ($account === null) {
            return self::FAILURE;
        }

        if ($account->is_root) {
            $this->error(__('privileges.errors.root'));

            return self::FAILURE;
        }

        $this->option('revoke') ? $roles->unassign($role, $account) : $roles->assign($role, $account);

        $this->info(__($this->option('revoke') ? 'users.messages.role_unassigned' : 'users.messages.role_assigned', ['role' => $role->name, 'name' => $account->name]));

        return self::SUCCESS;
    }
}
