<?php

use App\Ai\Tools\Database\ExportDatabase;
use App\Enums\OperatingSystemEnum;
use App\Models\User;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExporterManager;
use App\Services\Exporter\ExportFormat;
use App\Services\Settings\UserSettings;
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

    $this->project = $project;
    $this->workspace = $project->fill(['path' => $this->home.'/project'])->workspaceOf($project->user);
    $driver = $project->driver_config->getDriver();
    $driver->getConnection()->table('users')->insert([
        ['id' => 1, 'name' => 'Ada'],
        ['id' => 2, 'name' => "O'Hara"],
        ['id' => 3, 'name' => 'Grace'],
    ]);

    $this->tool = new ExportDatabase($driver, app(ExporterManager::class), $this->workspace);
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('exports the requested tables into the exports directory of the user in the project', function () {
    $result = json_decode((string) $this->tool->handle(new Request([
        'onlyTables' => [['table' => 'users', 'schema' => true, 'data' => false]],
        'format' => 'csv',
        'output' => 'zip',
    ], 'call_1')), true);

    expect($result)->toMatchArray(['success' => true, 'format' => 'csv', 'output' => 'zip', 'tables' => ['users']])
        ->and($result['path'])->toMatch('/^\.opendb\/users\/\d+\/exports\/shop_\d{8}_\d{6}\.zip$/')
        ->and($this->home.'/project/'.$result['path'])->toBeFile();
    expect($result['modified_at'])->toBe(date(DATE_ATOM, filemtime($this->home.'/project/'.$result['path'])));
    expect(file_get_contents($this->workspace->path('exports/.gitignore')))->toContain("\n*\n");

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

it('leaves the tables disabled for the agent out of an export of the whole database', function () {
    $this->project->driver_config->update(['disabled_tables' => ['logs']]);
    $tool = new ExportDatabase($this->project->driver_config->getAgentDriver(), app(ExporterManager::class), $this->workspace);

    $result = json_decode((string) $tool->handle(new Request(['format' => 'csv'], 'call_1')), true);

    $zip = new ZipArchive;
    $zip->open($this->home.'/project/'.$result['path']);
    $files = array_map(fn (int $index): string => $zip->getNameIndex($index), range(0, $zip->count() - 1));
    $zip->close();

    expect($result['success'])->toBeTrue()
        ->and($files)->toContain('users.csv')
        ->and($files)->not->toContain('logs.csv');
});

it('exports in the format and compression the user chose by default', function () {
    $settings = new UserSettings(exportFormat: ExportFormat::Csv, exportCompression: ExportCompression::Tar);
    $tool = new ExportDatabase($this->project->driver_config->getDriver(), app(ExporterManager::class), $this->workspace, $settings);

    $result = json_decode((string) $tool->handle(new Request(['onlyTables' => [['table' => 'users', 'schema' => true, 'data' => true]]], 'call_1')), true);

    expect($result)->toMatchArray(['success' => true, 'format' => 'csv', 'output' => 'tar'])
        ->and($result['path'])->toEndWith('.tar');
});

it('refuses to export a table disabled for the agent', function () {
    $this->project->driver_config->update(['disabled_tables' => ['logs']]);
    $tool = new ExportDatabase($this->project->driver_config->getAgentDriver(), app(ExporterManager::class), $this->workspace);

    $result = json_decode((string) $tool->handle(new Request(['onlyTables' => [['table' => 'logs', 'schema' => true, 'data' => true]]], 'call_1')), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain("La table [logs] est désactivée pour l'agent")
        ->and(is_dir($this->workspace->path('exports')))->toBeFalse();
});

it('exports the result of a query with its bindings into a single CSV file', function () {
    $result = json_decode((string) $this->tool->handle(new Request([
        'query' => 'SELECT id, name FROM users WHERE id > :id ORDER BY id DESC LIMIT 2',
        'bindings' => ['id' => 1],
    ], 'call_1')), true);

    expect($result)->toMatchArray(['success' => true, 'format' => 'csv', 'output' => 'zip', 'tables' => 'query', 'rows' => 2])
        ->and($result['path'])->toMatch('/^\.opendb\/users\/\d+\/exports\/shop_\d{8}_\d{6}\.zip$/');

    $zip = new ZipArchive;
    $zip->open($this->home.'/project/'.$result['path']);
    expect($zip->count())->toBe(1)
        ->and($zip->getNameIndex(0))->toEndWith('.csv')
        ->and($zip->getFromIndex(0))->toBe("id,name\n3,Grace\n2,O'Hara\n");
    $zip->close();
});

it('reads the bindings a model sent as text instead of an object', function (string $bindings, int $rows) {
    $result = json_decode((string) $this->tool->handle(new Request([
        'query' => 'SELECT id, name FROM users WHERE id > :id',
        'bindings' => $bindings,
    ], 'call_1')), true);

    expect($result)->toMatchArray(['success' => true, 'rows' => $rows]);
})->with([
    'json object' => ['{"id": 1}', 2],
]);

it('reads every row of a query larger than a chunk', function () {
    $result = json_decode((string) $this->tool->handle(new Request([
        'query' => 'WITH RECURSIVE numbers(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM numbers WHERE n < 1001) SELECT n FROM numbers',
    ], 'call_1')), true);

    expect($result)->toMatchArray(['success' => true, 'rows' => 1001]);
});

it('returns the error to the agent without writing an export when a query cannot be exported', function (array $arguments, string $error) {
    $result = json_decode((string) $this->tool->handle(new Request($arguments, 'call_1')), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain($error)
        ->and(is_dir($this->workspace->path('exports')))->toBeFalse();
})->with([
    'no row' => [['query' => 'SELECT * FROM users WHERE id = :id', 'bindings' => ['id' => 99]], 'la requête ne renvoie aucune ligne'],
    'write query' => [['query' => 'DELETE FROM users'], 'seules les instructions SELECT et WITH'],
    'sql format' => [['query' => 'SELECT * FROM users', 'format' => 'sql'], 'can only be exported as CSV'],
    'unreadable bindings' => [['query' => 'SELECT * FROM users', 'bindings' => '{}}'], '`bindings` must be a JSON object'],
    'query and tables' => [['query' => 'SELECT * FROM users', 'onlyTables' => [['table' => 'users', 'schema' => true, 'data' => true]]], 'cannot be combined'],
]);
