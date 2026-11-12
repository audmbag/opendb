<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Http\Requests\Users\RoleRequest;
use App\Repository\RoleRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('role:update
    {role? : console.commands.role_update.role}
    {--rename= : console.commands.role_update.rename}
    {--description= : console.commands.role_update.role_description}
    {--name= : console.options.name}')]
#[Description('console.commands.role_update.description')]
class RoleUpdate extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(RoleRepository $roles): int
    {
        if ($this->logIn() === null) {
            return self::FAILURE;
        }

        $role = $this->chooseRole($this->argument('role'));

        if ($role === null) {
            return self::FAILURE;
        }

        $asks = $this->option('rename') === null && $this->option('description') === null;

        $request = $this->validated(RoleRequest::class, [
            'name' => $this->option('rename') ?? ($asks ? text(__('users.fields.role'), default: $role->name, required: true) : $role->name),
            'description' => $this->option('description') ?? ($asks ? text(__('users.fields.description'), default: (string) $role->description) : $role->description),
        ], ['role' => $role]);

        if ($request === null) {
            return self::FAILURE;
        }

        $roles->update($role, $request->validated());

        $this->info(__('users.messages.role_updated', ['name' => $role->name]));

        return self::SUCCESS;
    }
}
