<?php

namespace App\Providers;

use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use App\Console\Help\HelpCommand;
use App\Console\Language\Prompts\Theme as PromptsTheme;
use App\Database\Schema\SnapshotBuilder;
use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Services\Cloning\Workflow\CloningWorkspaceFactory;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\McpAccessManager;
use App\Services\Privileges\PrivilegeManager;
use App\Services\Privileges\PrivilegeResolver;
use App\Services\SystemInfoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Application as Artisan;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Models\Conversation;

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

        $this->app->scoped(PrivilegeResolver::class);

        // The MCP server is started by mcp:serve or mcp:start: both give it the session of the token of the client.
        $this->app->singleton(McpSession::class, fn (): McpSession => $this->app->make(McpAccessManager::class)->authenticateFromEnvironment());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApp();
        $this->configureConsole();

        Gate::policy(Conversation::class, ConversationPolicy::class);

        $this->configurePrivileges();

        DevCommands::artisan('serve --host=localhost --no-reload', 'server');
    }

    /**
     * The root holds every privilege, like the root of MySQL; anyone else holds those granted to them or to their roles.
     */
    protected function configurePrivileges(): void
    {
        // The conversations stay private to who had them, even for the root.
        Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $user->is_root && ! (($arguments[0] ?? null) instanceof Conversation) ? true : null);

        Gate::define('privilege', fn (User $user, PrivilegeEnum $privilege, ?Project $project = null, ?string $table = null): bool => $this->app
            ->make(PrivilegeManager::class)
            ->for($user)
            ->allows($privilege, $project, $table));
    }

    /**
     * Configure the application's default settings.
     *
     * Point the "opendb" disk to the application directory of the current user (~/.opendb).
     * Point the log channels of the agent and of the MCP server to the same directory.
     */
    protected function configureApp(): void
    {
        config([
            'filesystems.disks.opendb.root' => $this->app->make(SystemInfoService::class)->applicationPath(),
        ]);

        config([
            'logging.channels.agent-logs.path' => $this->app->make(SystemInfoService::class)->logDirectory()->path('agent.log'),
            'logging.channels.mcp-logs.path' => $this->app->make(SystemInfoService::class)->logDirectory()->path('mcp.log'),
        ]);
    }

    /**
     * The console speaks the language of the system when the interface supports it, English otherwise, down to the
     * help of the commands and the messages of Laravel Prompts. The tests keep the language of APP_LOCALE, so that
     * they do not depend on the machine running them.
     */
    protected function configureConsole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! $this->app->runningUnitTests()) {
            App::setLocale($this->app->make(SystemInfoService::class)->language(array_keys(config('app.supported_locales'))));
        }

        PromptsTheme::use();

        Artisan::starting(fn (Artisan $artisan) => $artisan->addCommand(new HelpCommand));
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

        DevCommands::only('server', 'vite', 'queue');
    }
}
