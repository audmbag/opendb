<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\Role;
use App\Repository\RoleRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('role:delete
    {role? : console.commands.role_delete.role}
    {--force : console.commands.role_delete.force}
    {--name= : console.options.name}')]
#[Description('console.commands.role_delete.description')]
class RoleDelete extends Command
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

        if ($role === null) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm(trans_choice('users.questions.delete_role', $role->users()->count(), ['name' => $role->name]), default: false)) {
            return self::SUCCESS;
        }

        $roles->delete($role);

        $this->info(__('users.messages.role_deleted', ['name' => $role->name]));

        return self::SUCCESS;
    }
}
