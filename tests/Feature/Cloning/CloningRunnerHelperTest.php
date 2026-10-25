<?php

use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningResult;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\Exception\CloningException;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-run-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->runName = '2026-10-25_10-00-00_runTest1';
    $this->workingDirectory = app(SystemInfoService::class)->temporaryDirectory($this->runName)->path('');
    $this->sourcePath = $this->home.'/source.sqlite';
    $this->targetPath = $this->home.'/target.sqlite';
    touch($this->sourcePath);
    touch($this->targetPath);

    config(['database.connections.clone_source' => ['driver' => 'sqlite', 'database' => $this->sourcePath, 'foreign_key_constraints' => true]]);
    $source = DB::connection('clone_source');
    $source->statement('create table users (id integer primary key, email varchar(255) not null, name varchar(255) not null)');
    $source->statement('create table posts (id integer primary key, user_id integer not null references users(id), title varchar(255) not null)');
    $source->statement('create table comments (id integer primary key, post_id integer not null references posts(id), body text not null)');
    $source->table('users')->insert([
        ['id' => 1, 'email' => 'ada@example.com', 'name' => 'Ada'],
        ['id' => 2, 'email' => 'alan@example.com', 'name' => 'Alan'],
        ['id' => 3, 'email' => 'grace@example.com', 'name' => 'Grace'],
    ]);
    $source->table('posts')->insert([
        ['id' => 10, 'user_id' => 1, 'title' => 'Premier'],
        ['id' => 11, 'user_id' => 3, 'title' => 'Second'],
    ]);
    $source->table('comments')->insert(['id' => 100, 'post_id' => 10, 'body' => 'Bravo']);
    DB::purge('clone_source');

    file_put_contents($this->workingDirectory.'clonio.json', json_encode(['connections' => [
        'source' => ['type' => 'sqlite', 'database' => $this->sourcePath],
        'target' => ['type' => 'sqlite', 'database' => $this->targetPath],
    ]]));
});

afterEach(function () {
    DB::purge('clone_target');
    File::deleteDirectory($this->home);
});

/**
 * @param  array<string, array<string, mixed>>  $columnsByTable
 */
function writeCloningYaml(string $workingDirectory, array $columnsByTable, array $options = []): void
{
    file_put_contents($workingDirectory.'source.cloning.yaml', Yaml::dump([
        'version' => '1',
        'connection' => 'source',
        'options' => [
            'chunk_size' => 2,
            'enforce_column_types' => true,
            'drop_unknown_tables' => false,
            'drop_extra_columns' => false,
            'disable_foreign_key_checks' => true,
            'faker_locale' => 'fr_FR',
            ...$options,
        ],
        'tables' => array_map(fn (array $columns): array => [
            'rows' => ['strategy' => 'full', 'clear' => 'delete'],
            'columns' => $columns,
        ], $columnsByTable),
    ], 10, 2));
}

function keepColumns(string ...$columns): array
{
    return array_fill_keys($columns, ['strategy' => 'keep']);
}

function targetRows(string $targetPath, string $table): array
{
    config(['database.connections.clone_target' => ['driver' => 'sqlite', 'database' => $targetPath]]);

    return DB::connection('clone_target')->table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
}

it('copies every table into the target and anonymizes the configured columns', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => [
            'id' => ['strategy' => 'keep'],
            'email' => ['strategy' => 'static', 'value' => 'anonyme@example.com'],
            'name' => ['strategy' => 'mask', 'mask_char' => '*', 'visible_chars' => 1, 'preserve_format' => false],
        ],
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);

    $logPath = app(CloningRunnerHelper::class)->cloneNow($this->runName);

    expect(targetRows($this->targetPath, 'users'))->toBe([
        ['id' => 1, 'email' => 'anonyme@example.com', 'name' => 'A**'],
        ['id' => 2, 'email' => 'anonyme@example.com', 'name' => 'A***'],
        ['id' => 3, 'email' => 'anonyme@example.com', 'name' => 'G****'],
    ])->and(targetRows($this->targetPath, 'posts'))->toHaveCount(2)
        ->and(targetRows($this->targetPath, 'comments'))->toHaveCount(1);

    $result = CloningResult::for($logPath);
    expect($result->isComplete())->toBeTrue()
        ->and($result->isSuccessful())->toBeTrue()
        ->and($result->getTotalRows())->toBe(6)
        ->and($result->getSource())->toBe('source')
        ->and($result->getTarget())->toBe('target')
        ->and(basename($logPath))->toMatch('/^source_target_\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2}Z_process\.jsonl$/');
});

it('remaps primary keys and rewrites the foreign keys that reference them', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => [
            'id' => ['strategy' => 'remapping', 'arguments' => [
                ['use' => 'random_integer'],
                ['foreign_keys' => [['table' => 'posts', 'column' => 'user_id', 'self_referential' => false]]],
            ]],
            ...keepColumns('email', 'name'),
        ],
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);

    app(CloningRunnerHelper::class)->cloneNow($this->runName);

    $users = collect(targetRows($this->targetPath, 'users'))->keyBy('email');
    $posts = collect(targetRows($this->targetPath, 'posts'))->keyBy('title');

    expect($users->pluck('id')->sort()->values()->all())->not->toBe([1, 2, 3])
        ->and($posts['Premier']['user_id'])->toBe($users['ada@example.com']['id'])
        ->and($posts['Second']['user_id'])->toBe($users['grace@example.com']['id']);
});

it('skips the requested tables and the tables that depend on them', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => keepColumns('id', 'email', 'name'),
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);

    $result = CloningResult::for(app(CloningRunnerHelper::class)->cloneNow($this->runName, skipTables: ['posts']));

    expect(targetRows($this->targetPath, 'users'))->toHaveCount(3)
        ->and(array_column($result->getEntriesByEvent('table_skipped_by_flag', 'table_skipped_by_cascade'), 'event', 'table'))->toBe([
            'posts' => 'table_skipped_by_flag',
            'comments' => 'table_skipped_by_cascade',
        ]);
});

it('logs the rows that cannot be inserted and keeps cloning the table', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => [
            ...keepColumns('id', 'email'),
            'name' => ['strategy' => 'null'],
        ],
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);

    $result = CloningResult::for(app(CloningRunnerHelper::class)->cloneNow($this->runName));

    expect($result->getEntriesByEvent('row_skipped'))->toHaveCount(3)
        ->and($result->getEntriesByEvent('table_transfer_failed')[0]['table'] ?? null)->toBe('users')
        ->and($result->isSuccessful())->toBeFalse()
        ->and(targetRows($this->targetPath, 'posts'))->toHaveCount(2);
});

it('stops at the first failed table when break_on_failure is enabled', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => [
            ...keepColumns('id', 'email'),
            'name' => ['strategy' => 'null'],
        ],
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);

    $result = CloningResult::for(app(CloningRunnerHelper::class)->cloneNow($this->runName, ['break_on_failure' => true]));

    expect($result->getEntriesByEvent('table_transferred'))->toBe([])
        ->and(targetRows($this->targetPath, 'posts'))->toBe([]);
});

it('fails before cloning when the configuration is invalid', function () {
    file_put_contents($this->workingDirectory.'source.cloning.yaml', "version: '1'\nconnection: source\ntables: {}\n");

    expect(fn () => app(CloningRunnerHelper::class)->cloneNow($this->runName))
        ->toThrow(CloningException::class, "Missing required field: 'options'")
        ->and(is_dir($this->workingDirectory.'logs'))->toBeFalse();
});

it('fails before cloning when the source connection is not configured', function () {
    writeCloningYaml($this->workingDirectory, ['users' => keepColumns('id', 'email', 'name')]);
    file_put_contents($this->workingDirectory.'source.cloning.yaml', str_replace('connection: source', 'connection: missing', file_get_contents($this->workingDirectory.'source.cloning.yaml')));

    expect(fn () => app(CloningRunnerHelper::class)->cloneNow($this->runName))
        ->toThrow(CloningException::class, "Source connection 'missing' not found in clonio.json.")
        ->and(is_dir($this->workingDirectory.'logs'))->toBeFalse();
});
