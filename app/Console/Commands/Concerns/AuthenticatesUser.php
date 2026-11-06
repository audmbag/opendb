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
            'name' => $this->option('name') ?? text("Nom d'utilisateur", required: true),
            'password' => password('Mot de passe', required: true),
        ];

        if (! Auth::validate($credentials)) {
            $this->error('Identifiants incorrects.');

            return null;
        }

        return Auth::getLastAttempted();
    }
}
