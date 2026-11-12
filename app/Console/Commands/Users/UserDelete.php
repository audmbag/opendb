<?php

namespace App\Console\Commands\Users;

use App\Console\Commands\Concerns\ManagesAccounts;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Repository\UserRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('user:delete
    {user? : console.commands.user_delete.user}
    {--force : console.commands.user_delete.force}
    {--name= : console.options.name}')]
#[Description('console.commands.user_delete.description')]
class UserDelete extends Command
{
    use ManagesAccounts, TranslatesCommand;

    public function handle(UserRepository $users): int
    {
        $actor = $this->logIn();

        if ($actor === null) {
            return self::FAILURE;
        }

        $account = $this->chooseAccount($this->argument('user'), __('users.fields.user'));

        if ($account === null) {
            return self::FAILURE;
        }

        if ($actor->cannot('delete', $account) || $account->is_root) {
            $this->error($account->is_root ? __('users.errors.root_deleted') : __('users.errors.denied'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm(__('users.questions.delete', ['name' => $account->name]), default: false)) {
            return self::SUCCESS;
        }

        $users->delete($account);

        $this->info(__('users.messages.deleted', ['name' => $account->name]));

        return self::SUCCESS;
    }
}
