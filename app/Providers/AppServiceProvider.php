<?php

namespace App\Providers;

use AlbertoArena\Truss\Cache\SchemaCacheRepository;
use AlbertoArena\Truss\Export\Contracts\CommentReader;
use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use App\Database\Schema\CachedCommentReader;
use App\Database\Schema\SchemaSnapshotCache;
use App\Database\Schema\SnapshotBuilder;
use App\Services\Cloning\Workflow\CloningWorkspaceFactory;
use App\Services\SSH\TunnelPool;
use App\Services\SSH\TunnelUsage;
use App\Services\SystemInfoService;
use Carbon\CarbonImmutable;
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
        $this->app->bind(CommentReader::class, CachedCommentReader::class);
        $this->app->scoped(SchemaCacheRepository::class, SchemaSnapshotCache::class);

        // The masters held by this process are counted until it ends, whatever request or command opened them.
        $this->app->singleton(TunnelUsage::class);
        $this->app->singleton(TunnelPool::class);
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
     * Point the log channels of the agent and of the MCP server to the same directory.
     */
    protected function configureApp(): void
    {
        $systemInfo = $this->app->make(SystemInfoService::class);

        config([
            'filesystems.disks.opendb.root' => $systemInfo->applicationPath(),
            'logging.channels.agent-logs.path' => $systemInfo->logDirectory()->path('agent.log'),
            'logging.channels.mcp-logs.path' => $systemInfo->logDirectory()->path('mcp.log'),
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
    }
}
