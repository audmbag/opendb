<?php

use App\Enums\OperatingSystemEnum;
use App\Models\User;
use App\Services\Exporter\ExporterManager;
use App\Services\SystemInfoService;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-export-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $project = projectWithSqliteDatabase(User::factory()->create(), $this->home.'/shop.sqlite', function (Builder $schema) {
        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        });
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bio')->nullable();
        });
    });
    $this->driver = $project->driver_config->getDriver();
    $this->driver->getConnection()->table('users')->insert([
        ['id' => 1, 'name' => "O'Hara", 'bio' => "Ligne 1\nLigne 2, avec \"guillemets\""],
        ['id' => 2, 'name' => 'Ada', 'bio' => null],
    ]);
    $this->driver->getConnection()->table('posts')->insert(['id' => 10, 'user_id' => 2, 'title' => 'Premier']);

    $this->destination = $this->home.'/project/exports';
    $this->extracted = $this->home.'/extracted';
});

afterEach(function () {
    DB::purge('reimport');
    File::deleteDirectory($this->home);
});

function extractArchive(string $archive, string $directory): array
{
    File::ensureDirectoryExists($directory);

    if (str_ends_with($archive, '.zip')) {
        $zip = new ZipArchive;
        $zip->open($archive);
        $zip->extractTo($directory);
        $zip->close();
    } else {
        (new PharData($archive))->extractTo($directory, null, true);
    }

    return collect(File::files($directory))->map->getFilename()->sort()->values()->all();
}

function reimport(string $sqlFile, string $databasePath): Connection
{
    touch($databasePath);
    config(['database.connections.reimport' => ['driver' => 'sqlite', 'database' => $databasePath, 'foreign_key_constraints' => true]]);
    DB::connection('reimport')->getPdo()->exec(file_get_contents($sqlFile));

    return DB::connection('reimport');
}

it('exports every table into a SQL dump that recreates the database', function () {
    $archive = app(ExporterManager::class)->export($this->driver, [], 'sql', 'zip', $this->destination);

    expect(dirname($archive))->toBe($this->destination)
        ->and(basename($archive))->toMatch('/^shop_\d{8}_\d{6}\.zip$/');

    $files = extractArchive($archive, $this->extracted);
    expect($files)->toHaveCount(1)->and($files[0])->toEndWith('.sql');

    $database = reimport($this->extracted.'/'.$files[0], $this->home.'/reimport.sqlite');

    expect($database->table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe([
        ['id' => 1, 'name' => "O'Hara", 'bio' => "Ligne 1\nLigne 2, avec \"guillemets\""],
        ['id' => 2, 'name' => 'Ada', 'bio' => null],
    ])->and($database->table('posts')->get()->map(fn ($row) => (array) $row)->all())->toBe([
        ['id' => 10, 'user_id' => 2, 'title' => 'Premier'],
    ]);
});

it('exports only the requested schema or data of each table', function () {
    $archive = app(ExporterManager::class)->export($this->driver, [
        'posts' => ['schema' => true, 'data' => false],
        'users' => ['schema' => false, 'data' => true],
    ], 'sql', 'tar', $this->destination);

    $sql = File::get($this->extracted.'/'.extractArchive($archive, $this->extracted)[0]);

    expect($archive)->toEndWith('.tar')
        ->and($sql)->toContain('CREATE TABLE IF NOT EXISTS "posts"')
        ->not->toContain('CREATE TABLE IF NOT EXISTS "users"')
        ->toContain('INSERT OR REPLACE INTO "users"')
        ->not->toContain('INSERT OR REPLACE INTO "posts"');
});

it('exports one CSV file per table and their schema in schema.sql', function () {
    $archive = app(ExporterManager::class)->export($this->driver, ['users' => []], 'csv', 'zip', $this->destination);

    expect(extractArchive($archive, $this->extracted))->toBe(['schema.sql', 'users.csv'])
        ->and(File::get($this->extracted.'/schema.sql'))->toContain('CREATE TABLE IF NOT EXISTS "users"')->not->toContain('"posts"');

    $handle = fopen($this->extracted.'/users.csv', 'rb');
    $rows = [];
    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    expect($rows)->toBe([
        ['id', 'name', 'bio'],
        ['1', "O'Hara", "Ligne 1\nLigne 2, avec \"guillemets\""],
        ['2', 'Ada', ''],
    ]);
});

it('compresses a single file as is and several files as a tar archive in gzip', function (string $format, string $extension) {
    $archive = app(ExporterManager::class)->export($this->driver, [], $format, 'gzip', $this->destination);

    expect($archive)->toEndWith($extension)
        ->and(gzdecode(File::get($archive)))->toBeString()->not->toBeEmpty();
})->with([
    'sql dump' => ['sql', '.sql.gz'],
    'csv files' => ['csv', '.tar.gz'],
]);

it('rejects tables that do not exist without writing an export', function () {
    expect(fn () => app(ExporterManager::class)->export($this->driver, ['orders' => [], 'users' => []], 'sql', 'zip', $this->destination))
        ->toThrow(RuntimeException::class, "Ces tables n'existent pas dans la base de données : orders.")
        ->and(is_dir($this->destination))->toBeFalse();
});

it('rejects an unknown format or compression', function (string $format, string $output, string $message) {
    expect(fn () => app(ExporterManager::class)->export($this->driver, [], $format, $output, $this->destination))
        ->toThrow(RuntimeException::class, $message);
})->with([
    'format' => ['xml', 'zip', "Format d'export inconnu : xml."],
    'compression' => ['sql', 'rar', 'Format de compression inconnu : rar.'],
]);
