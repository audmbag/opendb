<?php

use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function cloningFlags(array $options = []): array
{
    return array_slice(app(CloningRunnerHelper::class)->getCloningCommand($options), 5);
}

it('builds the cloning command with the default options', function () {
    expect(app(CloningRunnerHelper::class)->getCloningCommand())->toBe([
        'clonio',
        'cloning:run',
        CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml',
        '--target',
        CloningManager::TARGET_CONNECTION_NAME,
        '--disable-fk-checks',
        '--no-drop-extra-columns',
        '--no-drop-unknown-tables',
        '--enforce-column-types',
    ]);
});

it('passes every enabled option', function () {
    expect(cloningFlags(array_fill_keys(array_keys(CloningRunnerHelper::CLONING_OPTIONS), true)))->toBe([
        '--disable-fk-checks',
        '--drop-extra-columns',
        '--drop-unknown-tables',
        '--enforce-column-types',
        '--break-on-failure',
    ]);
});

it('negates disabled options that have a negative form and omits the others', function () {
    expect(cloningFlags(array_fill_keys(array_keys(CloningRunnerHelper::CLONING_OPTIONS), false)))->toBe([
        '--no-disable-fk-checks',
        '--no-drop-extra-columns',
        '--no-drop-unknown-tables',
        '--no-enforce-column-types',
    ]);
});

it('ignores unknown options', function () {
    expect(cloningFlags(['faker_locale' => 'fr_FR', 'dry_run' => true]))->toBe(cloningFlags());
});

describe('firstJsonlFile', function () {
    beforeEach(function () {
        $this->directory = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-jsonl-');
        mkdir($this->directory);
    });

    afterEach(function () {
        File::deleteDirectory($this->directory);
    });

    it('returns the first jsonl file in alphabetical order', function () {
        touch($this->directory.'/b.jsonl');
        touch($this->directory.'/a.jsonl');
        touch($this->directory.'/0.log');

        expect(app(CloningRunnerHelper::class)->firstJsonlFile($this->directory))->toBe($this->directory.'/a.jsonl');
    });

    it('ignores jsonl files of sub directories', function () {
        mkdir($this->directory.'/nested');
        touch($this->directory.'/nested/audit.jsonl');

        expect(app(CloningRunnerHelper::class)->firstJsonlFile($this->directory))->toBeNull();
    });

    it('returns null when the directory does not exist', function () {
        expect(app(CloningRunnerHelper::class)->firstJsonlFile($this->directory.'/missing'))->toBeNull();
    });
});

describe('cloneConnection', function () {
    beforeEach(function () {
        $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-clone-');
        mkdir($this->home);

        $this->app->instance(SystemInfoService::class, new SystemInfoService(
            OperatingSystemEnum::current(),
            'OpenDB',
            ['HOME' => $this->home, 'USERPROFILE' => $this->home],
        ));

        $this->cloningId = '2026-10-13_14-30-00_a1B2c3D4';
        $this->logDirectory = app(SystemInfoService::class)->logDirectory()->path(CloningManager::CLONING_LOG_PATH.'/'.$this->cloningId);
    });

    afterEach(function () {
        File::deleteDirectory($this->home);
    });

    it('returns the jsonl audit log written by the cloning', function () {
        Process::fake(function () {
            File::ensureDirectoryExists($this->logDirectory);
            File::put($this->logDirectory.'/audit.jsonl', '{}');

            return Process::result();
        });

        expect(app(CloningRunnerHelper::class)->cloneConnection($this->cloningId, []))->toBe($this->logDirectory.'/audit.jsonl');

        Process::assertRan(fn ($process) => $process->path === app(SystemInfoService::class)->temporaryDirectory($this->cloningId)->path(''));
    });

    it('fails when the cloning wrote no jsonl log', function () {
        Process::fake();

        app(CloningRunnerHelper::class)->cloneConnection($this->cloningId, []);
    })->throws(CloningException::class, 'No cloning log file (.jsonl) found');

    it('fails when the cloning process fails', function () {
        Process::fake(fn () => Process::result(errorOutput: 'Connection refused', exitCode: 1));

        app(CloningRunnerHelper::class)->cloneConnection($this->cloningId, []);
    })->throws(CloningException::class, 'Failed to clone connection: Connection refused');
});
