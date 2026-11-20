<?php

use App\Enums\OperatingSystemEnum;
use App\Services\FileExplorerService;
use App\Services\SystemInfoService;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function () {
    $this->root = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('file-explorer-');
    $this->home = $this->root.DIRECTORY_SEPARATOR.'home';
    $this->outside = $this->root.DIRECTORY_SEPARATOR.'outside';

    mkdir($this->home.DIRECTORY_SEPARATOR.'projects', recursive: true);
    mkdir($this->outside, recursive: true);
    touch($this->home.DIRECTORY_SEPARATOR.'notes.txt');

    $this->systemInfo = new SystemInfoService(OperatingSystemEnum::current(), 'OpenDB', [
        'HOME' => $this->home,
        'USERPROFILE' => $this->home,
    ]);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('resolves the home directory through the system info service', function () {
    $service = new FileExplorerService(systemInfo: $this->systemInfo);

    expect($service->getUserHomeDirectory())->toBe($this->home);
});

it('explores the home directory by default', function () {
    $tree = (new FileExplorerService(systemInfo: $this->systemInfo))->getFolderTree();

    expect($tree['id'])->toBe($this->home)
        ->and(array_column($tree['children'], 'name'))->toBe(['projects', 'notes.txt']);
});

it('restricts access to the home directory by default', function () {
    (new FileExplorerService(systemInfo: $this->systemInfo))->getFolderTree($this->outside);
})->throws(RuntimeException::class, 'Accès refusé');

it('falls back to the real system when no system info service is given', function () {
    expect((new FileExplorerService)->getUserHomeDirectory())->toBe((new SystemInfoService)->homeDirectory());
});

it('only preloads two levels of the tree by default', function () {
    $database = implode(DIRECTORY_SEPARATOR, [$this->home, 'projects', 'app', 'database']);
    mkdir($database, recursive: true);
    touch($database.DIRECTORY_SEPARATOR.'data.sqlite');

    $tree = (new FileExplorerService(systemInfo: $this->systemInfo))->getFolderTree();

    expect($tree['children'][0]['children'][0]['name'])->toBe('app')
        ->and($tree['children'][0]['children'][0]['children'])->toBe([]);
});

it('preloads every folder leading to the revealed path', function () {
    $database = implode(DIRECTORY_SEPARATOR, [$this->home, 'projects', 'app', 'database']);
    mkdir($database, recursive: true);
    mkdir($this->home.DIRECTORY_SEPARATOR.'projects'.DIRECTORY_SEPARATOR.'other'.DIRECTORY_SEPARATOR.'nested', recursive: true);
    touch($database.DIRECTORY_SEPARATOR.'data.sqlite');

    $tree = (new FileExplorerService(systemInfo: $this->systemInfo))
        ->getFolderTree(reveal: $database.DIRECTORY_SEPARATOR.'data.sqlite');

    $projects = $tree['children'][0];
    $app = $projects['children'][0];
    $other = $projects['children'][1];

    expect($app['children'][0]['name'])->toBe('database')
        ->and(array_column($app['children'][0]['children'], 'id'))->toBe([$database.DIRECTORY_SEPARATOR.'data.sqlite'])
        ->and($other['children'])->toBe([]);
});

it('ignores a revealed path outside of the explored folder', function () {
    $tree = (new FileExplorerService(systemInfo: $this->systemInfo))->getFolderTree(reveal: $this->outside);

    expect(array_column($tree['children'], 'name'))->toBe(['projects', 'notes.txt']);
});

it('lists a file it cannot read, but not a folder it cannot open', function () {
    $locked = $this->home.DIRECTORY_SEPARATOR.'locked';
    mkdir($locked);
    chmod($locked, 0);
    chmod($this->home.DIRECTORY_SEPARATOR.'notes.txt', 0);

    $tree = (new FileExplorerService(systemInfo: $this->systemInfo))->getFolderTree();

    chmod($locked, 0700);

    expect(array_column($tree['children'], 'name'))->toBe(['projects', 'notes.txt']);
})->skip(PHP_OS_FAMILY === 'Windows' || posix_getuid() === 0, 'Permissions are not enforced for this user.');
