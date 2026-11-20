<?php

use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Data\CloningRequest;
use App\Services\Cloning\Workflow\CloningConnections;
use App\Services\Cloning\Workflow\CloningExecutor;
use App\Services\Cloning\Workflow\CloningPreparer;
use App\Services\Cloning\Workflow\CloningWorkspace;
use App\Services\SystemInfoService;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-keep-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->source = $this->home.'/source.sqlite';
    $this->target = $this->home.'/target.sqlite';
    $pdo = new PDO('sqlite:'.$this->source);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(191))');
    $pdo->exec("INSERT INTO users VALUES (1, 'ada@example.test'), (2, 'alan@example.test')");
    $pdo->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY, content BLOB)');
    $pdo->exec('WITH RECURSIVE c(x) AS (SELECT 1 UNION ALL SELECT x + 1 FROM c WHERE x < 20) INSERT INTO documents SELECT x, randomblob(1048576) FROM c');
    touch($this->target);

    $this->sourceConnection = CloningConnections::fromArray(CloningManager::SOURCE_CONNECTION_NAME, ['driver_id' => 'sqlite', 'database' => $this->source]);
    $this->cloningId = app(CloningPreparer::class)->openWorkspace($this->sourceConnection);
    $this->workspace = app(SystemInfoService::class)->temporaryDirectory($this->cloningId);
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('opens a cloning without reading the source', function () {
    expect($this->workspace->fileExists(CloningWorkspace::CONFIG_FILE))->toBeTrue()
        ->and($this->workspace->fileExists(CloningWorkspace::SOURCE_YAML_FILE))->toBeFalse();
});

it('copies every column as it is when the automatic anonymisation was not asked for', function () {
    $yamlPath = $this->workspace->path(CloningWorkspace::SOURCE_YAML_FILE);
    $yaml = null;

    // Le dossier du clonage est supprimé après la copie : la configuration est lue au moment où le moteur démarre.
    Event::listen(ConnectionEstablished::class, function () use ($yamlPath, &$yaml): void {
        $yaml ??= is_file($yamlPath) ? Yaml::parseFile($yamlPath) : null;
    });

    app(CloningExecutor::class)->execute(new CloningRequest(
        cloningId: $this->cloningId,
        source: $this->sourceConnection,
        target: CloningConnections::fromArray(CloningManager::TARGET_CONNECTION_NAME, ['driver_id' => 'sqlite', 'database' => $this->target]),
        clientDraft: ['users' => ['email' => ['strategy' => 'null']]],
        automaticAnonymisation: false,
    ));

    expect(collect($yaml['tables'])->flatMap(fn (array $table): array => array_column($table['columns'], 'strategy'))->unique()->values()->all())->toBe(['keep'])
        ->and($yaml['options']['chunk_size'])->toBeBetween(14, 16)
        ->and(new PDO('sqlite:'.$this->target)->query('SELECT email FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))->toBe(['ada@example.test', 'alan@example.test']);
});
