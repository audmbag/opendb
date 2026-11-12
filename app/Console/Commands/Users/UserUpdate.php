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

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('user:update
    {user? : console.commands.user_update.user}
    {--rename= : console.commands.user_update.rename}
    {--password : console.commands.user_update.password}
    {--role=* : console.commands.user_update.role}
    {--name= : console.options.name}')]
#[Description('console.commands.user_update.description')]
class UserUpdate extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(UserRepository $users): int
    {
        if ($this->logIn() === null) {
            return self::FAILURE;
        }

        $account = $this->chooseAccount($this->argument('user'), __('users.fields.user'));

        if ($account === null) {
            return self::FAILURE;
        }

        $asks = $this->option('rename') === null && ! $this->option('password') && $this->option('role') === [];

        $answers = ['name' => $this->option('rename') ?? ($asks ? text(__('users.fields.name'), default: $account->name, required: true) : $account->name)];

        if ($this->option('password') || ($asks && confirm(__('users.questions.change_password'), default: false))) {
            $answers['password'] = password(__('users.fields.password'), required: true);
            $answers['password_confirmation'] = password(__('users.fields.password_confirmation'), required: true);
        }

        $roleNames = match (true) {
            $this->option('role') !== [] => $this->option('role'),
            $asks => array_values(multiselect(__('users.fields.roles'), Role::query()->orderBy('name')->pluck('name', 'name')->all(), default: $account->roles()->pluck('name')->all(), scroll: 10)),
            default => null,
        };

        if ($roleNames !== null) {
            $answers['roles'] = $this->roleIds($roleNames);

            if ($answers['roles'] === null) {
                return self::FAILURE;
            }
        }

        $request = $this->validated(UserRequest::class, $answers, ['user' => $account]);

        if ($request === null) {
            return self::FAILURE;
        }

        $users->update($account, [...$request->validated(), 'roles' => $roleNames === null ? null : $request->validated('roles', [])]);

        $this->info(__('users.messages.updated', ['name' => $account->name]));

        return self::SUCCESS;
    }
}
