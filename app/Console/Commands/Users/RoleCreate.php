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

#[Signature('role:create
    {role? : console.commands.role_create.role}
    {--description= : console.commands.role_create.role_description}
    {--name= : console.options.name}')]
#[Description('console.commands.role_create.description')]
class RoleCreate extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(RoleRepository $roles): int
    {
        if ($this->logIn() === null) {
            return self::FAILURE;
        }

        $request = $this->validated(RoleRequest::class, [
            'name' => $this->argument('role') ?? text(__('users.fields.role'), required: true),
            'description' => $this->option('description') ?? ($this->argument('role') === null ? text(__('users.fields.description')) : null),
        ]);

        if ($request === null) {
            return self::FAILURE;
        }

        $role = $roles->create($request->validated());

        $this->info(__('users.messages.role_created', ['name' => $role->name]));

        return self::SUCCESS;
    }
}
