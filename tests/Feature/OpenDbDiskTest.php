<?php

use App\Enums\OperatingSystemEnum;
use App\Providers\AppServiceProvider;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-disk-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->app->getProvider(AppServiceProvider::class)->boot();
    Storage::forgetDisk('opendb');
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('points the opendb disk to the application directory by default', function () {
    $this->refreshApplication();

    expect(config('filesystems.disks.opendb.root'))->toBe((new SystemInfoService)->applicationPath());
});

it('roots the opendb disk in the application directory of the user', function () {
    $root = app(SystemInfoService::class)->applicationPath();

    expect(Storage::disk('opendb')->path('database.sqlite'))->toBe($root.DIRECTORY_SEPARATOR.'database.sqlite')
        ->and($root)->toBe($this->home.DIRECTORY_SEPARATOR.'.opendb')->toBeDirectory();
});

it('reads and writes files through the opendb disk', function () {
    $disk = Storage::disk('opendb');

    expect($disk->put('tmp/a1b2c3/clonio.json', '{"tables":[]}'))->toBeTrue()
        ->and($this->home.'/.opendb/tmp/a1b2c3/clonio.json')->toBeFile()
        ->and($disk->json('tmp/a1b2c3/clonio.json'))->toBe(['tables' => []])
        ->and($disk->deleteDirectory('tmp/a1b2c3'))->toBeTrue()
        ->and($disk->exists('tmp/a1b2c3/clonio.json'))->toBeFalse();
});

it('keeps the files of the opendb disk private to the current user', function () {
    Storage::disk('opendb')->put('logs/cloning/run.log', 'started');

    expect(fileperms($this->home.'/.opendb') & 0777)->toBe(0700)
        ->and(fileperms($this->home.'/.opendb/logs/cloning') & 0777)->toBe(0700)
        ->and(fileperms($this->home.'/.opendb/logs/cloning/run.log') & 0777)->toBe(0600);
})->skipOnWindows();
