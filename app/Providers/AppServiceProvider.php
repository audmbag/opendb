<?php

namespace App\Providers;

use AlbertoArena\Truss\Introspection\SnapshotBuilder as TrussSnapshotBuilder;
use App\Console\Help\HelpCommand;
use App\Console\InstalledConsole;
use App\Console\Language\Prompts\Theme as PromptsTheme;
use App\Database\Schema\SnapshotBuilder;
use App\Enums\PrivilegeEnum;
use App\Models\Project;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Services\Cloning\Workflow\CloningWorkspaceFactory;
use App\Services\Installation\InstallationManager;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\McpAccessManager;
use App\Services\Privileges\PrivilegeManager;
use App\Services\Privileges\PrivilegeResolver;
use App\Services\SSH\TunnelUsage;
use App\Services\SystemInfoService;
use App\Support\ApplicationHome;
use Carbon\CarbonImmutable;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Laravel\Ai\Models\Conversation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The HTTP statuses that have their own error page in the interface.
     *
     * @var list<int>
     */
    public const array ERROR_PAGES = [403, 404, 419, 429, 500, 503];

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

        // The masters held by this process are counted until it ends, whatever request or command opened them.
        $this->app->singleton(TunnelUsage::class);

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
        $this->upgradeInstallation();

        Gate::policy(Conversation::class, ConversationPolicy::class);

        $this->configurePrivileges();
        $this->configureErrorPages();

        DevCommands::artisan('serve --host=localhost --no-reload', 'server');
    }

    /**
     * An installed copy brings the database of the user up to its version before it serves anything.
     */
    protected function upgradeInstallation(): void
    {
        if ($this->app->bound(ApplicationHome::class)) {
            $this->app->booted(fn () => $this->app->make(InstallationManager::class)->upgradeIfNeeded());
        }
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
     * The errors met while browsing the interface are told by its own error page, with the shared data of the pages.
     * The requests expecting JSON keep their JSON answer, and a server error keeps the detailed report of Laravel
     * while debugging.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $error): ?ExceptionResponse {
            $status = $error->statusCode();

            if (! in_array($status, self::ERROR_PAGES, true) || $error->response instanceof JsonResponse || $error->request->expectsJson()) {
                return null;
            }

            if ($status === 500 && config('app.debug')) {
                return null;
            }

            return $error->render('error', ['status' => $status])->withSharedData();
        });
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

        Artisan::starting(function (Artisan $artisan): void {
            $artisan->setName((string) config('app.name'));
            $artisan->setVersion((string) config('app.version'));
            $artisan->addCommand(new HelpCommand);
        });

        // Once installed, the user reaches the commands of OpenDB only.
        if ($this->app->bound(ApplicationHome::class)) {
            $console = $this->app->make(InstalledConsole::class);
            Artisan::starting($console->prepare(...));
            Event::listen(CommandStarting::class, $console->guard(...));
        }
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
