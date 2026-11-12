<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Asks who runs the command, with the credentials of the login page. The password is only ever asked through
 * a hidden prompt, so that it stays out of the shell history and of the process list.
 */
trait AuthenticatesUser
{
    protected function authenticateUser(): ?User
    {
        $credentials = [
            'name' => $this->option('name') ?? text(__('login.username'), required: true),
            'password' => password(__('login.password'), required: true),
        ];

        if (! Auth::validate($credentials)) {
            $this->error(__('console.invalid_credentials'));

            return null;
        }

        return Auth::getLastAttempted();
    }
}
