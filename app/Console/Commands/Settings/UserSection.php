<?php

namespace App\Console\Commands\Settings;

use App\Models\User;

/**
 * A section of the global settings of the user.
 */
abstract class UserSection extends Section
{
    abstract public function run(User $user): void;
}
