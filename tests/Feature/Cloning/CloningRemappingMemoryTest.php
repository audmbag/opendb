<?php

use App\Database\Drivers\BaseDriver;
use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\Data\CloningRequest;
use App\Services\Cloning\Exception\CloningException;
use App\Services\Cloning\Workflow\CloningConnections;
use App\Services\Cloning\Workflow\CloningExecutor;
use App\Services\Cloning\Workflow\CloningPreparer;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-remapping-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->runner = Mockery::mock(CloningRunnerHelper::class);
    $this->app->instance(CloningRunnerHelper::class, $this->runner);

    $this->source = $this->home.'/source.sqlite';
    $pdo = new PDO('sqlite:'.$this->source);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(191))');
    $pdo->exec('CREATE TABLE comments (id INTEGER PRIMARY KEY, body TEXT)');
    $pdo->exec("WITH RECURSIVE c(x) AS (SELECT 1 UNION ALL SELECT x + 1 FROM c WHERE x < 200000) INSERT INTO comments SELECT x, 'commentaire' FROM c");
    $pdo->exec("INSERT INTO users VALUES (1, 'a@example.test'), (2, 'b@example.test')");

    $this->cloningId = '2026-10-20_13-00-00_rmpMemry';
    $this->sourceConnection = CloningConnections::fromArray(CloningManager::SOURCE_CONNECTION_NAME, ['driver_id' => 'sqlite', 'database' => $this->source]);
    $this->payload = collect(app(CloningPreparer::class)->clientPayload($this->sourceConnection, $this->cloningId)['tables'])->keyBy('name');
    $this->yaml = Yaml::parseFile(app(SystemInfoService::class)->temporaryDirectory($this->cloningId)->path('source.cloning.yaml'));
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('keeps the identifiers of a table too large to be remapped and tells the client', function () {
    expect($this->yaml['tables']['comments']['columns']['id'])->toBe(['strategy' => 'keep'])
        ->and($this->yaml['tables']['users']['columns']['id']['strategy'])->toBe('remapping')
        ->and($this->payload['comments']['remappingNotice'])->toBe(__('cloning.remapping_notices.too_many_rows', ['rows' => "200\u{202F}000"]))
        ->and($this->payload['users']['remappingNotice'])->toBeNull();
});

it('sizes the chunks from the sqlite page statistics', function () {
    $pdo = new PDO('sqlite:'.$this->source);
    $pdo->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY, content BLOB)');
    $pdo->exec('WITH RECURSIVE c(x) AS (SELECT 1 UNION ALL SELECT x + 1 FROM c WHERE x < 20) INSERT INTO documents SELECT x, randomblob(1048576) FROM c');
    BaseDriver::for(['driver_id' => 'sqlite', 'database' => $this->source])->forgetSchema();

    $cloningId = app(CloningPreparer::class)->clientPayload($this->sourceConnection)['cloningId'];

    $yaml = Yaml::parseFile(app(SystemInfoService::class)->temporaryDirectory($cloningId)->path('source.cloning.yaml'));

    expect($this->yaml['options']['chunk_size'])->toBe(1000)
        ->and($yaml['options']['chunk_size'])->toBeBetween(14, 16);
});

it('refuses before running clonio a remapping chosen by hand that exceeds its memory', function () {
    $this->runner->shouldNotReceive('cloneNow');

    $clone = fn () => app(CloningExecutor::class)->execute(new CloningRequest(
        cloningId: $this->cloningId,
        source: $this->sourceConnection,
        target: CloningConnections::fromArray(CloningManager::TARGET_CONNECTION_NAME, ['driver_id' => 'sqlite', 'database' => $this->home.'/target.sqlite']),
        clientDraft: ['comments' => ['id' => ['strategy' => 'remapping', 'remappingUse' => 'random_integer', 'foreignKeys' => []]]],
    ));

    expect($clone)->toThrow(CloningException::class, __('cloning.remapping_notices.exceeds_memory', ['tables' => 'comments']));
});
