<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ChoosesPrivileges;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('privilege:grant
    {grantee? : console.commands.privilege_grant.grantee}
    {--role : console.commands.privilege_grant.role}
    {--privilege=* : console.commands.privilege_grant.privilege}
    {--project= : console.commands.privilege_grant.project}
    {--table= : console.commands.privilege_grant.table}
    {--with-grant-option : console.commands.privilege_grant.with_grant_option}
    {--name= : console.options.name}')]
#[Description('console.commands.privilege_grant.description')]
class PrivilegeGrant extends Command
{
    use ChoosesPrivileges, TranslatesCommand;

    public function handle(PrivilegeManager $privileges): int
    {
        return $this->changePrivileges(
            fn (User $actor, User|Role $grantee, array $granted, ?Project $project, ?string $table) => $privileges->grant($actor, $grantee, $granted, $project, $table, (bool) $this->option('with-grant-option')),
            __('users.messages.granted'),
        );
    }
}
