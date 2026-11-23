<?php

namespace App\Providers;

use App\Console\Help\HelpCommand;
use App\Console\InstalledConsole;
use App\Console\Language\Prompts\Theme as PromptsTheme;
use App\Services\SystemInfoService;
use App\Support\ApplicationHome;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the console of OpenDB.
     */
    public function boot(): void
    {
        $this->configureConsole();
        $this->configureDevCommands();
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
     * The servers "php artisan dev" starts. An installed copy has no development command: its console refuses those of
     * Laravel.
     */
    protected function configureDevCommands(): void
    {
        if ($this->app->bound(ApplicationHome::class)) {
            return;
        }

        DevCommands::artisan('serve --host=localhost --no-reload', 'server');
        DevCommands::only('server', 'vite');
    }
}
