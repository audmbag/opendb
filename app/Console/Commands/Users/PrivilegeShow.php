<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ChoosesPrivileges;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('privilege:show
    {grantee? : console.commands.privilege_show.grantee}
    {--role : console.commands.privilege_show.role}
    {--name= : console.options.name}')]
#[Description('console.commands.privilege_show.description')]
class PrivilegeShow extends Command
{
    use ChoosesPrivileges, TranslatesCommand;

    public function handle(): int
    {
        $actor = $this->logIn();

        if ($actor === null) {
            return self::FAILURE;
        }

        $asksForOthers = $this->option('role') || ($this->argument('grantee') !== null && $this->argument('grantee') !== $actor->name);

        // Like SHOW GRANTS FOR in MySQL, the privileges of someone else are only shown to who manages the accounts.
        if ($asksForOthers && $actor->cannot('viewAny', User::class)) {
            $this->error(__('users.errors.denied'));

            return self::FAILURE;
        }

        $grantee = match (true) {
            $this->option('role') => $this->chooseRole($this->argument('grantee')),
            $this->argument('grantee') === null => $actor,
            default => $this->chooseAccount($this->argument('grantee'), __('users.fields.user')),
        };

        if ($grantee === null) {
            return self::FAILURE;
        }

        $this->showGrants($grantee);

        return self::SUCCESS;
    }
}
