<?php

use App\Enums\OperatingSystemEnum;
use App\Models\Provider;
use App\Models\User;
use App\Services\Installation\DatabaseBackup;
use App\Services\Installation\InstallationManager;
use App\Services\SystemInfoService;
use App\Support\ApplicationHome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->root = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-install-');
    mkdir($this->root.'/app', recursive: true);
    mkdir($this->root.'/home/.opendb/config', recursive: true);

    $this->home = ApplicationHome::detect($this->root.'/app', null, new SystemInfoService(OperatingSystemEnum::LINUX, ApplicationHome::NAME, ['HOME' => $this->root.'/home']));
    $this->app->instance(ApplicationHome::class, $this->home);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/**
 * The database of the user as a file, whose copy can be compared with it.
 */
function databaseFile(string $contents): string
{
    $database = test()->root.'/home/.opendb/database.sqlite';
    file_put_contents($database, $contents);
    config(['database.connections.sqlite.database' => $database]);

    return $database;
}

it('creates the providers and the root account on the first launch', function () {
    app(InstallationManager::class)->upgradeIfNeeded();

    $root = User::query()->sole();

    expect(Provider::query()->toBase()->orderBy('id')->pluck('id')->all())->toBe(['anthropic', 'deepseek', 'gemini', 'groq', 'mistral', 'ollama', 'openai', 'openrouter', 'xai'])
        ->and($root->name)->toBe('opendb')
        ->and($root->is_root)->toBeTrue()
        ->and(Hash::check('opendb', $root->password))->toBeTrue()
        ->and(file_get_contents($this->root.'/home/.opendb/config/release'))->toBe($this->home->release());
});

it('does nothing more once the folder of the user is up to the version', function () {
    app(InstallationManager::class)->upgradeIfNeeded();
    Provider::query()->delete();

    app(InstallationManager::class)->upgradeIfNeeded();

    expect(Provider::query()->count())->toBe(0);
});

it('adds the providers of a new version and keeps the keys already given', function () {
    file_put_contents($this->root.'/home/.opendb/config/release', 'previous-version');
    Provider::query()->create(['id' => 'anthropic', 'label' => 'Anthropic', 'api_key' => 'sk-ant-kept']);
    User::factory()->create(['name' => 'jane']);

    app(InstallationManager::class)->upgradeIfNeeded();

    expect(Provider::query()->find('anthropic')->api_key)->toBe('sk-ant-kept')
        ->and(Provider::query()->count())->toBe(9)
        ->and(User::query()->pluck('name')->all())->toBe(['jane']);
});

it('copies the database before a new version changes it', function () {
    $this->freezeTime();
    databaseFile('the data of the previous version');

    app(InstallationManager::class)->upgradeIfNeeded();

    expect(file_get_contents($this->root.'/home/.opendb/backups/database-'.now()->format('Y-m-d_His').'.sqlite'))->toBe('the data of the previous version');
});

it('copies the changes still in the log of a database written ahead', function () {
    $this->freezeTime();
    $database = databaseFile('');
    $writer = DB::build(['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'journal_mode' => 'wal']);
    $writer->statement('CREATE TABLE notes (body TEXT)');
    $writer->table('notes')->insert(['body' => 'written last']);

    app(InstallationManager::class)->upgradeIfNeeded();
    $writer->disconnect();

    $copy = DB::build(['driver' => 'sqlite', 'database' => $this->root.'/home/.opendb/backups/database-'.now()->format('Y-m-d_His').'.sqlite', 'prefix' => '']);

    expect($copy->table('notes')->pluck('body')->all())->toBe(['written last']);

    $copy->disconnect();
});

it('keeps the copies of the latest versions only', function () {
    databaseFile('the data of the previous version');
    mkdir($this->root.'/home/.opendb/backups');
    foreach (['2026-01-01_000000', '2026-02-01_000000', '2026-03-01_000000', '2026-04-01_000000', '2026-05-01_000000'] as $date) {
        touch($this->root."/home/.opendb/backups/database-{$date}.sqlite");
    }

    app(InstallationManager::class)->upgradeIfNeeded();

    expect(File::files($this->root.'/home/.opendb/backups'))->toHaveCount(DatabaseBackup::KEPT)
        ->and($this->root.'/home/.opendb/backups/database-2026-01-01_000000.sqlite')->not->toBeFile();
});

it('clears the caches of the previous versions', function () {
    mkdir($this->home->bootstrapCacheDirectory(), recursive: true);
    mkdir(dirname($this->home->bootstrapCacheDirectory()).'/previous-version');

    app(InstallationManager::class)->upgradeIfNeeded();

    expect(dirname($this->home->bootstrapCacheDirectory()).'/previous-version')->not->toBeDirectory()
        ->and($this->home->bootstrapCacheDirectory())->toBeDirectory();
});

it('removes the scripts OPcache compiled to disk once no copy does so any more', function () {
    if ((string) ini_get('opcache.file_cache') !== '') {
        $this->markTestSkipped('This PHP compiles its scripts to disk.');
    }

    mkdir($this->root.'/home/.opendb/cache/opcache/system/C/Users/someone', recursive: true);
    touch($this->root.'/home/.opendb/cache/opcache/system/C/Users/someone/artisan.bin');

    app(InstallationManager::class)->upgradeIfNeeded();

    expect($this->root.'/home/.opendb/cache/opcache')->not->toBeDirectory();
});

it('removes the copies the previous versions of the binary unpacked', function () {
    $unpacked = $this->root.'/home/.opendb/tmp';
    mkdir($unpacked.'/frankenphp_1000_current', recursive: true);
    mkdir($unpacked.'/frankenphp_1000_previous');
    mkdir($unpacked.'/export-2026-11-19');
    $this->app->instance(ApplicationHome::class, ApplicationHome::detect($unpacked.'/frankenphp_1000_current', null, new SystemInfoService(OperatingSystemEnum::LINUX, ApplicationHome::NAME, ['HOME' => $this->root.'/home'])));

    app(InstallationManager::class)->upgradeIfNeeded();

    expect($unpacked.'/frankenphp_1000_previous')->not->toBeDirectory()
        ->and($unpacked.'/frankenphp_1000_current')->toBeDirectory()
        ->and($unpacked.'/export-2026-11-19')->toBeDirectory();
});

it('leaves alone what lies next to a copy outside the folder of the user', function () {
    mkdir($this->root.'/frankenphp_1000_other');

    app(InstallationManager::class)->upgradeIfNeeded();

    expect($this->root.'/frankenphp_1000_other')->toBeDirectory();
});
