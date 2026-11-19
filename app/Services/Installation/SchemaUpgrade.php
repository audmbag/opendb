<?php

declare(strict_types=1);

namespace App\Services\Installation;

use Illuminate\Support\Facades\Artisan;

/**
 * The migrations of the version run on the database of the user, created empty on the first launch.
 */
final readonly class SchemaUpgrade
{
    public function run(): void
    {
        $database = config('database.connections.sqlite.database');

        if (is_string($database) && $database !== ':memory:' && ! is_file($database)) {
            touch($database);
        }

        Artisan::call('migrate', ['--force' => true]);
    }
}
