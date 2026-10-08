<?php

use App\Enums\OperatingSystemEnum;
use App\Services\FileExplorerService;
use App\Services\SystemInfoService;

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
    unlink($this->home.DIRECTORY_SEPARATOR.'notes.txt');
    rmdir($this->home.DIRECTORY_SEPARATOR.'projects');
    rmdir($this->home);
    rmdir($this->outside);
    rmdir($this->root);
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
