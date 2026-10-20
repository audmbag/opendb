<?php

use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-manager-logs-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->cloningId = '2026-10-18_08-40-31_twhAYYXJ';
    $this->tmpDirectory = app(SystemInfoService::class)->temporaryDirectory($this->cloningId);
    $this->logFile = 'source_target_2026-10-18T08-40-44Z_process.jsonl';

    $this->tmpDirectory->put('clonio.json', '{}');
    $this->tmpDirectory->put('logs/'.$this->logFile, '{"event":"run_started"}');
    $this->tmpDirectory->put('logs/source_target_2026-10-18T08-40-44Z_audit.pdf', 'pdf');

    $this->manager = app(CloningManager::class);
    $this->manager->tmpDirectory = $this->cloningId;
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('moves the jsonl log into the logs directory and removes the temporary directory', function () {
    $temporaryPath = $this->tmpDirectory->path('');

    $newPath = $this->manager->clearMoveLogsAndTemporaryDirectory();

    expect($newPath)->toBe(app(SystemInfoService::class)->applicationPath('logs', $this->cloningId, $this->logFile))
        ->and(File::get($newPath))->toBe('{"event":"run_started"}')
        ->and(File::exists($temporaryPath))->toBeFalse();
});

it('fails when the log file does not exist', function () {
    $this->tmpDirectory->delete('logs/'.$this->logFile);

    $this->manager->clearMoveLogsAndTemporaryDirectory();
})->throws(CloningException::class, 'No cloning log file (.jsonl) found');

it('fails when the cloning id is not set', function () {
    $this->manager->tmpDirectory = null;

    $this->manager->clearMoveLogsAndTemporaryDirectory();
})->throws(CloningException::class, 'Cloning id is not set');
