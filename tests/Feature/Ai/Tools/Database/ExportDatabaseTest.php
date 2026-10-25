<?php

use App\Ai\Tools\Database\ExportDatabase;
use App\Enums\OperatingSystemEnum;
use App\Models\User;
use App\Services\Exporter\ExporterManager;
use App\Services\SystemInfoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-export-tool-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $project = projectWithSqliteDatabase(User::factory()->create(), $this->home.'/shop.sqlite', function (Builder $schema) {
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $schema->create('logs', function (Blueprint $table) {
            $table->id();
        });
    });

    $this->tool = new ExportDatabase($project->driver_config->getDriver(), app(ExporterManager::class), $this->home.'/project');
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('exports the requested tables into the exports directory of the project', function () {
    $result = json_decode((string) $this->tool->handle(new Request([
        'onlyTables' => [['table' => 'users', 'schema' => true, 'data' => false]],
        'format' => 'csv',
        'output' => 'zip',
    ], 'call_1')), true);

    expect($result)->toMatchArray(['success' => true, 'format' => 'csv', 'output' => 'zip', 'tables' => ['users']])
        ->and($result['path'])->toMatch('/^exports\/shop_\d{8}_\d{6}\.zip$/')
        ->and($this->home.'/project/'.$result['path'])->toBeFile();

    $zip = new ZipArchive;
    $zip->open($this->home.'/project/'.$result['path']);
    expect($zip->count())->toBe(1)->and($zip->getNameIndex(0))->toBe('schema.sql');
    $zip->close();
});

it('returns the error to the agent when a table does not exist', function () {
    $result = json_decode((string) $this->tool->handle(new Request(['onlyTables' => [['table' => 'orders', 'schema' => true, 'data' => true]]], 'call_1')), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toStartWith("Ces tables n'existent pas dans la base de données : orders.");
});
