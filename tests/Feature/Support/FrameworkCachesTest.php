<?php

use App\Support\FrameworkCaches;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Facades\File;

/**
 * The event service provider of an application of the tests, listing its listeners without scanning a folder.
 */
final class FrameworkCachesTestEvents extends EventServiceProvider
{
    protected $listen = ['App\Events\ProjectOpened' => ['App\Listeners\RecordOpening']];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

beforeEach(function () {
    $this->root = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('opendb-caches-');
    mkdir($this->root);
    $_SERVER['APP_CONFIG_CACHE'] = $this->root.DIRECTORY_SEPARATOR.'config.php';
    $_SERVER['APP_EVENTS_CACHE'] = $this->root.DIRECTORY_SEPARATOR.'events.php';
});

afterEach(function () {
    unset($_SERVER['APP_CONFIG_CACHE'], $_SERVER['APP_EVENTS_CACHE'], $_SERVER['APP_URL'], $_SERVER['OPENDB_BINARY']);
    File::deleteDirectory($this->root);
});

/**
 * An application of its own, with the given configuration: creating one makes it the current container, which the test
 * gets back.
 *
 * @param  array<string, mixed>  $configuration
 */
function applicationWithConfiguration(string $basePath, array $configuration): Application
{
    $current = Application::getInstance();
    $app = new Application($basePath);
    Application::setInstance($current);
    $app->addAbsoluteCachePathPrefix($basePath);
    $app->instance('files', new Filesystem);
    $app->instance('config', new Repository($configuration));

    return $app;
}

it('caches the configuration for the next processes', function () {
    $app = applicationWithConfiguration($this->root, ['app' => ['name' => 'OpenDB', 'url' => 'http://127.0.0.1:8000']]);

    new FrameworkCaches()->cacheConfiguration($app);

    expect(require $_SERVER['APP_CONFIG_CACHE'])->toBe(['app' => ['name' => 'OpenDB', 'url' => 'http://127.0.0.1:8000']]);
});

it('keeps the configuration another process cached', function () {
    file_put_contents($_SERVER['APP_CONFIG_CACHE'], '<?php return ["app" => ["name" => "Cached"]];');
    $app = applicationWithConfiguration($this->root, ['app' => ['name' => 'OpenDB']]);

    new FrameworkCaches()->cacheConfiguration($app);

    expect(require $_SERVER['APP_CONFIG_CACHE'])->toBe(['app' => ['name' => 'Cached']]);
});

it('leaves the configuration uncached when PHP cannot write a value of it back as code', function () {
    $app = applicationWithConfiguration($this->root, ['app' => ['resolver' => fn (): string => 'OpenDB']]);

    new FrameworkCaches()->cacheConfiguration($app);

    expect(File::files($this->root))->toBe([]);
});

it('gives a cached configuration the settings the launcher passed to this process', function () {
    file_put_contents($_SERVER['APP_CONFIG_CACHE'], '<?php return [];');
    $_SERVER['APP_URL'] = 'http://127.0.0.1:9000';
    $_SERVER['OPENDB_BINARY'] = '/home/someone/.local/bin/opendb';
    $app = applicationWithConfiguration($this->root, ['app' => ['url' => 'http://127.0.0.1:8000'], 'opendb' => ['binary' => 'opendb']]);

    new FrameworkCaches()->applyProcessSettings($app);

    expect($app['config']->get('app.url'))->toBe('http://127.0.0.1:9000')
        ->and($app['config']->get('opendb.binary'))->toBe('/home/someone/.local/bin/opendb');
});

it('leaves the settings of a configuration read from its files', function () {
    $_SERVER['APP_URL'] = 'http://127.0.0.1:9000';
    $app = applicationWithConfiguration($this->root, ['app' => ['url' => 'http://localhost']]);

    new FrameworkCaches()->applyProcessSettings($app);

    expect($app['config']->get('app.url'))->toBe('http://localhost');
});

it('forgets the cached configuration once the .env file of the user changes', function (int $environmentChange, bool $forgotten) {
    $environment = $this->root.DIRECTORY_SEPARATOR.'.env';
    file_put_contents($environment, 'APP_KEY=base64:key');
    file_put_contents($_SERVER['APP_CONFIG_CACHE'], '<?php return [];');
    touch($_SERVER['APP_CONFIG_CACHE'], 1_700_000_000);
    touch($environment, 1_700_000_000 + $environmentChange);

    new FrameworkCaches()->forgetConfigurationOlderThan($environment, $_SERVER['APP_CONFIG_CACHE']);

    expect(is_file($_SERVER['APP_CONFIG_CACHE']))->toBe(! $forgotten);
})->with([
    'changed after the cache' => [60, true],
    'unchanged since' => [-60, false],
]);

it('caches the listeners of each event service provider', function () {
    $app = applicationWithConfiguration($this->root, []);
    $app->register(new FrameworkCachesTestEvents($app));

    new FrameworkCaches()->cacheEvents($app);

    expect(require $_SERVER['APP_EVENTS_CACHE'])->toBe([FrameworkCachesTestEvents::class => ['App\Events\ProjectOpened' => ['App\Listeners\RecordOpening']]]);
});
