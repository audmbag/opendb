<?php

use App\Enums\OperatingSystemEnum;
use App\Services\SystemInfoService;
use App\Support\ApplicationHome;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-home-');
    $this->base = $this->root.'/app';
    $this->home = $this->root.'/home';
    mkdir($this->base.'/vendor/composer', recursive: true);
    mkdir($this->home);
    touch($this->base.'/vendor/composer/installed.php');
    $this->system = new SystemInfoService(OperatingSystemEnum::LINUX, ApplicationHome::NAME, ['HOME' => $this->home]);
});

afterEach(function () {
    foreach (['DB_DATABASE', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE'] as $name) {
        unset($_SERVER[$name], $_ENV[$name]);
    }

    File::deleteDirectory($this->root);
});

/**
 * An application of its own for the copy: creating one makes it the current container, which the test gets back.
 */
function installedApplication(string $basePath): Application
{
    $current = Application::getInstance();
    $app = new Application($basePath);
    Application::setInstance($current);

    return $app;
}

it('keeps the folders of the project in a checkout with its own .env', function () {
    touch($this->base.'/.env');

    expect(ApplicationHome::detect($this->base, 'local', $this->system))->toBeNull();
});

it('keeps the folders of the project for the tests', function () {
    expect(ApplicationHome::detect($this->base, 'testing', $this->system))->toBeNull();
});

it('points an installed copy to the folder of the user', function () {
    $app = installedApplication($this->base);
    $home = ApplicationHome::detect($this->base, null, $this->system);

    $home->apply($app);

    expect($app->environmentPath())->toBe($this->home.'/.opendb/config')
        ->and($app->storagePath())->toBe($this->home.'/.opendb/storage')
        ->and($app->make(ApplicationHome::class))->toBe($home)
        ->and($home->environmentVariables())->toMatchArray(['DB_DATABASE' => $this->home.'/.opendb/database.sqlite', 'QUEUE_CONNECTION' => 'deferred'])
        ->and($_SERVER['APP_CONFIG_CACHE'])->toBe($home->bootstrapCacheDirectory().'/config.php')
        ->and($home->bootstrapCacheDirectory())->toStartWith($this->home.'/.opendb/cache/bootstrap/')
        ->and($this->home.'/.opendb/storage/framework/views')->toBeDirectory()
        ->and($this->home.'/.opendb/storage/logs')->toBeDirectory();
});

it('draws the key once, readable by the user alone', function () {
    ApplicationHome::detect($this->base, null, $this->system)->apply(installedApplication($this->base));
    $environment = file_get_contents($this->home.'/.opendb/config/.env');

    ApplicationHome::detect($this->base, null, $this->system)->apply(installedApplication($this->base));

    expect($environment)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=\R$/')
        ->and(file_get_contents($this->home.'/.opendb/config/.env'))->toBe($environment)
        ->and(fileperms($this->home.'/.opendb/config/.env') & 0777)->toBe(0600);
});

it('lets the variables the process received win', function () {
    $_SERVER['APP_CONFIG_CACHE'] = '/srv/opendb/config.php';

    ApplicationHome::detect($this->base, null, $this->system)->apply(installedApplication($this->base));

    expect($_SERVER['APP_CONFIG_CACHE'])->toBe('/srv/opendb/config.php');
});

it('gives each new version caches of its own', function () {
    $home = ApplicationHome::detect($this->base, null, $this->system);
    $release = $home->release();

    touch($this->base.'/vendor/composer/installed.php', time() + 60);
    clearstatcache();

    expect($home->release())->not->toBe($release)
        ->and($home->bootstrapCacheDirectory())->toBe($this->home.'/.opendb/cache/bootstrap/'.$home->release());
});
