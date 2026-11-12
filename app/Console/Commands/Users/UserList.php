<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\User;
use App\Repository\UserRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

#[Signature('user:list {--name= : console.options.name}')]
#[Description('console.commands.user_list.description')]
class UserList extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(UserRepository $users): int
    {
        $actor = $this->logIn();

        if ($actor === null) {
            return self::FAILURE;
        }

        if ($actor->cannot('viewAny', User::class)) {
            $this->error(__('users.errors.denied'));

            return self::FAILURE;
        }

        table(
            [__('users.fields.name'), __('users.fields.roles')],
            $users->list()->map(fn (User $user): array => [
                $user->is_root ? "{$user->name} (root)" : $user->name,
                $user->roles->pluck('name')->join(', '),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
