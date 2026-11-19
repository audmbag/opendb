<?php

namespace App\Console;

/**
 * How the user starts the commands, to tell them which one to type: opendb once installed, php artisan in a checkout.
 */
final class ConsoleScript
{
    public static function name(): string
    {
        // The launcher of the binary runs the artisan script itself, which its name would tell.
        if (filled(config('opendb.binary'))) {
            return 'opendb';
        }

        $script = basename($_SERVER['argv'][0] ?? 'artisan');

        return $script === 'artisan' ? 'php artisan' : $script;
    }
}
