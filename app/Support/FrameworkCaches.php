<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Env;

/**
 * The configuration and the events of an installed copy, cached in its folder by the first process that runs it: the
 * next ones read one file each, instead of some forty files of configuration and a scan of app/Listeners. On a hard
 * disk under Windows, each file read cold costs tens of milliseconds.
 *
 * The configuration is taken once the service providers are registered, before they boot: what they set while booting,
 * like the language of the console, is set again by each process. A process reads the cache as the commands of Laravel
 * write it, the folder of the user changing nothing.
 */
final readonly class FrameworkCaches
{
    /**
     * The settings each process receives from the launcher, which the cache holds as the process writing it had them.
     *
     * @var array<string, string>
     */
    public const array PROCESS_SETTINGS = ['app.url' => 'APP_URL', 'opendb.binary' => 'OPENDB_BINARY'];

    public function attach(Application $app): void
    {
        $this->forgetConfigurationOlderThan($app->environmentFilePath(), $app->getCachedConfigPath());

        $app->afterBootstrapping(LoadConfiguration::class, $this->applyProcessSettings(...));
        $app->afterBootstrapping(RegisterProviders::class, $this->cacheConfiguration(...));
        $app->booted($this->cacheEvents(...));
    }

    /**
     * A key or a setting changed in the .env file of the user holds from the next process on.
     */
    public function forgetConfigurationOlderThan(string $environmentFile, string $cache): void
    {
        if (is_file($cache) && (int) @filemtime($environmentFile) > (int) @filemtime($cache)) {
            @unlink($cache);
        }
    }

    public function applyProcessSettings(Application $app): void
    {
        if (! $app->configurationIsCached()) {
            return;
        }

        foreach (self::PROCESS_SETTINGS as $key => $variable) {
            if (($value = Env::get($variable)) !== null) {
                $app['config']->set($key, $value);
            }
        }
    }

    public function cacheConfiguration(Application $app): void
    {
        if (! $app->configurationIsCached()) {
            $this->write($app->getCachedConfigPath(), $app['config']->all());
        }
    }

    /**
     * The listeners of each event service provider, as event:cache writes them.
     */
    public function cacheEvents(Application $app): void
    {
        if ($app->eventsAreCached()) {
            return;
        }

        $events = [];

        foreach ($app->getProviders(EventServiceProvider::class) as $provider) {
            $events[$provider::class] = array_merge_recursive($provider->shouldDiscoverEvents() ? $provider->discoverEvents() : [], $provider->listens());
        }

        $this->write($app->getCachedEventsPath(), $events);
    }

    /**
     * Written aside then moved in place: a process starting meanwhile reads the whole file or none. A value PHP cannot
     * write back as code, like a closure, leaves the cache unwritten.
     *
     * @param  array<array-key, mixed>  $values
     */
    private function write(string $path, array $values): void
    {
        if (! $this->isExportable($values)) {
            return;
        }

        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($temporary, '<?php return '.var_export($values, true).';'.PHP_EOL) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);
        }
    }

    private function isExportable(mixed $value): bool
    {
        return is_array($value) ? array_all($value, $this->isExportable(...)) : ! is_object($value) && ! is_resource($value);
    }
}
