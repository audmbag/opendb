<?php

namespace App\Providers;

use App\Services\Installation\InstallationManager;
use App\Support\ApplicationHome;
use Illuminate\Support\ServiceProvider;

class InstallationServiceProvider extends ServiceProvider
{
    /**
     * An installed copy brings the database of the user up to its version before it serves anything.
     */
    public function boot(): void
    {
        if ($this->app->bound(ApplicationHome::class)) {
            $this->app->booted(fn () => $this->app->make(InstallationManager::class)->upgradeIfNeeded());
        }
    }
}
