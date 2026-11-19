<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Whether the user signed in on the web still has the password every installation starts with. Checking a password
 * costs a hash: it is done once per session, and again once the account changes.
 */
class DefaultPassword
{
    private const string SESSION_KEY = 'default_password';

    public function isUsed(User $user, Session $session): bool
    {
        if (! $session->has(self::SESSION_KEY)) {
            $session->put(self::SESSION_KEY, $user->hasDefaultPassword());
        }

        return (bool) $session->get(self::SESSION_KEY);
    }

    public function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
