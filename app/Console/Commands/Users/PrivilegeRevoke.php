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

#[Signature('privilege:revoke
    {grantee? : console.commands.privilege_revoke.grantee}
    {--role : console.commands.privilege_revoke.role}
    {--privilege=* : console.commands.privilege_revoke.privilege}
    {--project= : console.commands.privilege_revoke.project}
    {--table= : console.commands.privilege_revoke.table}
    {--grant-option : console.commands.privilege_revoke.grant_option}
    {--name= : console.options.name}')]
#[Description('console.commands.privilege_revoke.description')]
class PrivilegeRevoke extends Command
{
    use ChoosesPrivileges, TranslatesCommand;

    public function handle(PrivilegeManager $privileges): int
    {
        return $this->changePrivileges(
            fn (User $actor, User|Role $grantee, array $revoked, ?Project $project, ?string $table) => $privileges->revoke($actor, $grantee, $revoked, $project, $table, (bool) $this->option('grant-option')),
            __('users.messages.revoked'),
        );
    }
}
