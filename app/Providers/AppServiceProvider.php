<?php

namespace App\Providers;

use App\Services\Cloning\CloningManager;
use App\Services\SystemInfoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(CloningManager::class)
            ->needs('$cloning_config_path')
            ->give(resource_path('app/clonio.json'))
            ->needs('$pii_matchers_path')
            ->give(resource_path('app/clonio.pii-matchers.yaml'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApp();
    }

    /**
     * Configure the application's default settings.
     *
     * Point the "opendb" disk to the application directory of the current user (~/.opendb).
     * Point the "opendb" log channel to the same directory.
     */
    protected function configureApp(): void
    {
        config([
            'filesystems.disks.opendb.root' => $this->app->make(SystemInfoService::class)->applicationPath(),
        ]);

        config([
            'logging.channels.agent-logs.path' => $this->app->make(SystemInfoService::class)->logDirectory()->path('agent.log'),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        DevCommands::only('server', 'vite');
    }
}
