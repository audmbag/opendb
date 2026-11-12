<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Http\Requests\Users\UserRequest;
use App\Models\Role;
use App\Repository\UserRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('user:create
    {user? : console.commands.user_create.user}
    {--role=* : console.commands.user_create.role}
    {--name= : console.options.name}')]
#[Description('console.commands.user_create.description')]
class UserCreate extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(UserRepository $users): int
    {
        if ($this->logIn() === null) {
            return self::FAILURE;
        }

        $answers = [
            'name' => $this->argument('user') ?? text(__('users.fields.name'), required: true),
            'password' => password(__('users.fields.password'), required: true),
            'password_confirmation' => password(__('users.fields.password_confirmation'), required: true),
        ];

        $roleNames = $this->option('role') !== [] ? $this->option('role') : $this->askRoles();
        $roles = $this->roleIds($roleNames);

        if ($roles === null) {
            return self::FAILURE;
        }

        $request = $this->validated(UserRequest::class, [...$answers, 'roles' => $roles]);

        if ($request === null) {
            return self::FAILURE;
        }

        $user = $users->create($request->validated());

        $this->info(__('users.messages.created', ['name' => $user->name]));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function askRoles(): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name', 'name')->all();

        return $roles === [] ? [] : array_values(multiselect(__('users.fields.roles'), $roles, scroll: 10, hint: __('users.hints.roles')));
    }
}
