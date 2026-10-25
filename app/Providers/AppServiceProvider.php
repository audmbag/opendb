<?php

namespace App\Providers;

use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use App\Database\Schema\SnapshotBuilder;
use App\Services\Cloning\Workflow\CloningWorkspaceFactory;
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
        $this->app->when(CloningWorkspaceFactory::class)
            ->needs('$configTemplatePath')
            ->give(resource_path('app/clonio.json'));
        $this->app->when(CloningWorkspaceFactory::class)
            ->needs('$piiMatchersPath')
            ->give(resource_path('app/clonio.pii-matchers.yaml'));

        $this->app->bind(TrussSnapshotBuilder::class, SnapshotBuilder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApp();

        DevCommands::artisan('serve --host=localhost --no-reload', 'server');
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
