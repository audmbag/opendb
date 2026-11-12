<?php

use App\Enums\OperatingSystemEnum;
use App\Services\SystemInfoService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class);

function systemInfo(OperatingSystemEnum $os, array $environment): SystemInfoService
{
    return new SystemInfoService($os, 'OpenDB', $environment);
}

function diskRoot(FilesystemAdapter $disk): string
{
    return rtrim($disk->path(''), '/\\');
}

it('maps the PHP OS family to an operating system', function (string $family, OperatingSystemEnum $expected) {
    expect(OperatingSystemEnum::fromFamily($family))->toBe($expected);
})->with([
    'windows' => ['Windows', OperatingSystemEnum::WINDOWS],
    'mac' => ['Darwin', OperatingSystemEnum::MAC],
    'linux' => ['Linux', OperatingSystemEnum::LINUX],
    'bsd falls back to linux' => ['BSD', OperatingSystemEnum::LINUX],
]);

it('detects the current operating system', function () {
    $system = new SystemInfoService(appName: 'OpenDB');

    expect($system->os())->toBe(OperatingSystemEnum::fromFamily(PHP_OS_FAMILY))
        ->and($system->homeDirectory())->toBeDirectory()
        ->and($system->tempDirectory())->toBeDirectory();
});

it('exposes which operating system is targeted', function () {
    $system = systemInfo(OperatingSystemEnum::MAC, ['HOME' => '/Users/jane']);

    expect($system->isMac())->toBeTrue()
        ->and($system->isLinux())->toBeFalse()
        ->and($system->isWindows())->toBeFalse();
});

it('resolves paths in a single hidden directory of the home directory', function (OperatingSystemEnum $os, array $environment, string $home, string $separator) {
    $system = systemInfo($os, $environment);
    $root = $home.$separator.'.opendb';

    expect($system->homeDirectory())->toBe($home)
        ->and($system->applicationPath())->toBe($root)
        ->and($system->applicationPath('database.sqlite'))->toBe($root.$separator.'database.sqlite')
        ->and($system->applicationPath('logs', 'cloning', 'run.log'))->toBe($root.$separator.'logs'.$separator.'cloning'.$separator.'run.log');
})->with([
    'linux' => [OperatingSystemEnum::LINUX, ['HOME' => '/home/jane'], '/home/jane', '/'],
    'mac' => [OperatingSystemEnum::MAC, ['HOME' => '/Users/jane'], '/Users/jane', '/'],
    'windows' => [OperatingSystemEnum::WINDOWS, ['USERPROFILE' => 'C:\Users\jane'], 'C:\Users\jane', '\\'],
    'windows home drive' => [OperatingSystemEnum::WINDOWS, ['HOMEDRIVE' => 'C:', 'HOMEPATH' => '\Users\jane'], 'C:\Users\jane', '\\'],
]);

it('ignores the XDG base directories on linux', function () {
    $system = systemInfo(OperatingSystemEnum::LINUX, [
        'HOME' => '/home/jane',
        'XDG_DATA_HOME' => '/data',
        'XDG_CONFIG_HOME' => '/config',
        'XDG_CACHE_HOME' => '/cache',
        'XDG_STATE_HOME' => '/state',
    ]);

    expect($system->applicationPath())->toBe('/home/jane/.opendb');
});

it('ignores the app data directories on windows', function () {
    $system = systemInfo(OperatingSystemEnum::WINDOWS, [
        'USERPROFILE' => 'C:\Users\jane',
        'APPDATA' => 'D:\Roaming',
        'LOCALAPPDATA' => 'D:\Local',
    ]);

    expect($system->applicationPath())->toBe('C:\Users\jane\.opendb');
});

it('derives the directory name from the application name', function (string $appName, string $expected) {
    $system = new SystemInfoService(OperatingSystemEnum::LINUX, $appName, ['HOME' => '/home/jane']);

    expect($system->applicationPath())->toBe($expected);
})->with([
    'mixed case' => ['OpenDB', '/home/jane/.opendb'],
    'with spaces' => ['Open DB', '/home/jane/.open-db'],
]);

it('throws when the application name gives no usable directory name', function () {
    (new SystemInfoService(OperatingSystemEnum::LINUX, '!!!', ['HOME' => '/home/jane']))->applicationPath();
})->throws(RuntimeException::class, 'Unable to derive a directory name');

it('throws when the home directory cannot be determined', function (OperatingSystemEnum $os) {
    systemInfo($os, [])->homeDirectory();
})->with(OperatingSystemEnum::cases())->throws(RuntimeException::class);

it('reads the machine locale from the environment', function (array $environment, string $expected) {
    expect(systemInfo(OperatingSystemEnum::LINUX, $environment)->locale())->toBe($expected);
})->with([
    'lang' => [['LANG' => 'fr_FR.UTF-8'], 'fr_FR'],
    'lc_messages over lang' => [['LC_MESSAGES' => 'de_DE.UTF-8', 'LANG' => 'fr_FR.UTF-8'], 'de_DE'],
    'lc_all over everything' => [['LC_ALL' => 'es_ES.UTF-8', 'LC_MESSAGES' => 'de_DE.UTF-8', 'LANG' => 'fr_FR.UTF-8'], 'es_ES'],
    'skips the C locale' => [['LC_ALL' => 'C', 'LANG' => 'fr_FR.UTF-8'], 'fr_FR'],
    'skips the POSIX locale' => [['LC_ALL' => 'POSIX', 'LC_MESSAGES' => 'C.UTF-8', 'LANG' => 'en_GB.UTF-8'], 'en_GB'],
]);

it('normalizes the machine locale', function (string $locale, string $expected) {
    expect(systemInfo(OperatingSystemEnum::LINUX, ['LANG' => $locale])->locale())->toBe($expected);
})->with([
    'without encoding' => ['en_US', 'en_US'],
    'other encoding' => ['pt_BR.ISO-8859-1', 'pt_BR'],
    'modifier' => ['sr_RS@latin', 'sr_RS'],
    'encoding and modifier' => ['ca_ES.UTF-8@valencia', 'ca_ES'],
    'hyphen' => ['fr-FR', 'fr_FR'],
    'wrong case' => ['FR_fr', 'fr_FR'],
    'script' => ['zh_Hans_CN', 'zh_CN'],
    'variant' => ['en_US_POSIX', 'en_US'],
    'numeric region' => ['es_419', 'es_419'],
    'three letter language' => ['fil_PH.UTF-8', 'fil_PH'],
    'language only' => ['fr', 'fr'],
]);

it('falls back to the default locale when the machine locale is unknown', function (array $environment) {
    $system = systemInfo(OperatingSystemEnum::LINUX, $environment);

    expect($system->locale())->toBe('en_US')
        ->and($system->locale('fr_FR'))->toBe('fr_FR');
})->with([
    'nothing set' => [[]],
    'C locale' => [['LANG' => 'C']],
    'C locale with encoding' => [['LANG' => 'C.UTF-8']],
    'POSIX locale' => [['LC_ALL' => 'POSIX']],
    'garbage' => [['LANG' => '???']],
]);

it('gives the language of the machine among the supported ones, or else the default one', function (array $environment, string $expected) {
    expect(systemInfo(OperatingSystemEnum::LINUX, $environment)->language(['fr', 'en']))->toBe($expected);
})->with([
    'supported language' => [['LANG' => 'fr_FR.UTF-8'], 'fr'],
    'supported language of another country' => [['LANG' => 'en_GB.UTF-8'], 'en'],
    'unsupported language' => [['LANG' => 'de_DE.UTF-8'], 'en'],
    'unknown language' => [['LANG' => 'C'], 'en'],
]);

it('detects the locale of the current machine', function () {
    expect((new SystemInfoService)->locale())->toMatch('/^[a-z]{2,3}(_([A-Z]{2}|\\d{3}))?$/');
});

describe('disks', function () {
    beforeEach(function () {
        $this->home = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('system-info-');
        mkdir($this->home);

        $this->root = $this->home.DIRECTORY_SEPARATOR.'.opendb';
        $this->system = systemInfo(OperatingSystemEnum::current(), ['HOME' => $this->home, 'USERPROFILE' => $this->home]);
    });

    afterEach(function () {
        File::deleteDirectory($this->home);
    });

    it('does not create anything when only resolving paths', function () {
        $this->system->homeDirectory();
        $this->system->applicationPath('logs');

        expect(scandir($this->home))->toBe(['.', '..']);
    });

    it('returns a disk rooted in the expected directory, creating it', function (string $method, string $directory) {
        expect($this->root.$directory)->not->toBeDirectory();

        $disk = $this->system->{$method}();

        expect($disk)->toBeInstanceOf(FilesystemAdapter::class)
            ->and(diskRoot($disk))->toBe($this->root.$directory)->toBeDirectory();
    })->with([
        'data' => ['dataDirectory', ''],
        'config' => ['configDirectory', DIRECTORY_SEPARATOR.'config'],
        'cache' => ['cacheDirectory', DIRECTORY_SEPARATOR.'cache'],
        'logs' => ['logDirectory', DIRECTORY_SEPARATOR.'logs'],
    ]);

    it('reads and writes files through the returned disk', function () {
        $logs = $this->system->logDirectory();

        expect($logs->put('cloning/run.log', 'started'))->toBeTrue()
            ->and($logs->path('cloning/run.log'))->toBe($this->root.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'cloning/run.log')->toBeFile()
            ->and($logs->get('cloning/run.log'))->toBe('started')
            ->and($this->system->dataDirectory()->exists('logs/cloning/run.log'))->toBeTrue();
    });

    it('returns a disk for a named temporary directory', function () {
        $disk = $this->system->temporaryDirectory('a1b2c3');
        $disk->put('clonio.json', '{"tables":[]}');

        expect(diskRoot($disk))->toBe($this->root.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'a1b2c3')
            ->and($disk->json('clonio.json'))->toBe(['tables' => []])
            ->and($this->system->dataDirectory()->deleteDirectory('tmp/a1b2c3'))->toBeTrue()
            ->and($this->root.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'a1b2c3')->not->toBeDirectory();
    });

    it('resolves the database path, creating its parent directory but not the file', function () {
        $path = $this->system->databasePath();

        expect($path)->toBe($this->root.DIRECTORY_SEPARATOR.'database.sqlite')->not->toBeFile()
            ->and($this->system->databasePath('app.sqlite'))->toBe($this->root.DIRECTORY_SEPARATOR.'app.sqlite')
            ->and($this->root)->toBeDirectory();
    });

    it('resolves a log path, creating its parent directory but not the file', function () {
        $path = $this->system->logPath('cloning/run.log');
        $directory = $this->root.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'cloning';

        expect($path)->toBe($directory.DIRECTORY_SEPARATOR.'run.log')->not->toBeFile()
            ->and($directory)->toBeDirectory();
    });

    it('keeps an existing directory untouched', function () {
        $this->system->dataDirectory()->put('database.sqlite', 'data');

        expect($this->system->dataDirectory()->get('database.sqlite'))->toBe('data');
    });

    it('restricts created directories and files to the current user', function () {
        $this->system->logDirectory()->put('cloning/run.log', 'started');

        expect(fileperms($this->root) & 0777)->toBe(0700)
            ->and(fileperms($this->root.'/logs') & 0777)->toBe(0700)
            ->and(fileperms($this->root.'/logs/cloning') & 0777)->toBe(0700)
            ->and(fileperms($this->root.'/logs/cloning/run.log') & 0777)->toBe(0600);
    })->skipOnWindows();

    it('refuses names that escape the base directory', function (string $method, string $name) {
        try {
            $this->system->{$method}($name);
        } finally {
            expect(scandir($this->home))->toBe(['.', '..']);
        }
    })->with(['temporaryDirectory', 'logPath'])->with([
        'empty' => [''],
        'parent' => ['..'],
        'nested parent' => ['cloning/../../secrets'],
        'windows parent' => ['cloning\\..\\secrets'],
        'absolute' => ['/etc/passwd'],
        'current' => ['./run'],
    ])->throws(InvalidArgumentException::class, 'Invalid relative path');

    it('throws when the directory cannot be created', function () {
        touch($this->home.DIRECTORY_SEPARATOR.'.opendb');

        $this->system->logDirectory();
    })->throws(Exception::class);
});
