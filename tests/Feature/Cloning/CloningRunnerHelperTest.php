<?php

use App\Enums\OperatingSystemEnum;
use App\Services\Cloning\CloningResult;
use App\Services\Cloning\CloningRunnerHelper;
use App\Services\Cloning\Engine\Cloning\CloningMonitor;
use App\Services\Cloning\Engine\Enums\CloningPhase;
use App\Services\Cloning\Engine\Exceptions\CloningCancelledException;
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

/**
 * Suivi qui note ce que le moteur lui signale et demande l'arrêt après `$cancelAfterRows` lignes copiées.
 */
function recordingMonitor(int $cancelAfterRows = PHP_INT_MAX): CloningMonitor
{
    return new class($cancelAfterRows) implements CloningMonitor
    {
        /** @var list<array<int, mixed>> */
        public array $tables = [];

        /** @var list<int> */
        public array $copiedRows = [];

        public function __construct(private readonly int $cancelAfterRows) {}

        public function phaseStarted(CloningPhase $phase): void {}

        public function tableStarted(string $table, int $position, int $tableCount, ?int $expectedRows): void
        {
            $this->tables[] = [$table, $position, $tableCount, $expectedRows];
        }

        public function rowsCopied(int $rows): void
        {
            $this->copiedRows[] = $rows;
        }

        public function isCancelled(): bool
        {
            return array_sum($this->copiedRows) >= $this->cancelAfterRows;
        }
    };
}

/**
 * Colonnes de users dont le nom est masqué : la table passe par PHP, lot par lot, au lieu d'être copiée par la base.
 */
function maskedUsers(): array
{
    return [...keepColumns('id', 'email'), 'name' => ['strategy' => 'mask', 'mask_char' => '*', 'visible_chars' => 1, 'preserve_format' => false]];
}

it('tells the monitor each table it copies and the rows copied so far', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => maskedUsers(),
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);
    $monitor = recordingMonitor();

    app(CloningRunnerHelper::class)->cloneNow($this->runName, monitor: $monitor);

    expect($monitor->tables)->toBe([
        ['users', 1, 3, 3],
        ['posts', 2, 3, 2],
        ['comments', 3, 3, 1],
    ])->and($monitor->copiedRows)->toBe([2, 3, 2, 1]);
});

it('stops between two chunks when the monitor cancels, without writing the log', function () {
    writeCloningYaml($this->workingDirectory, [
        'users' => maskedUsers(),
        'posts' => keepColumns('id', 'user_id', 'title'),
        'comments' => keepColumns('id', 'post_id', 'body'),
    ]);

    expect(fn () => app(CloningRunnerHelper::class)->cloneNow($this->runName, monitor: recordingMonitor(cancelAfterRows: 2)))
        ->toThrow(CloningCancelledException::class)
        ->and(targetRows($this->targetPath, 'users'))->toHaveCount(2)
        ->and(targetRows($this->targetPath, 'posts'))->toBe([])
        ->and(is_dir($this->workingDirectory.'logs'))->toBeFalse();
});

it('lets SQLite copy at once the tables kept as they are', function () {
    config(['database.connections.clone_source' => ['driver' => 'sqlite', 'database' => $this->sourcePath]]);
    DB::connection('clone_source')->statement('create table events (id integer primary key, label text not null)');
    DB::connection('clone_source')->statement("with recursive n(x) as (select 1 union all select x + 1 from n where x < 60000) insert into events select x, 'event '||x from n");
    DB::purge('clone_source');

    writeCloningYaml($this->workingDirectory, ['events' => keepColumns('id', 'label')], ['chunk_size' => 1000]);
    $monitor = recordingMonitor();

    $result = CloningResult::for(app(CloningRunnerHelper::class)->cloneNow($this->runName, monitor: $monitor));

    expect($monitor->copiedRows)->toBe([60000])
        ->and($result->getTotalRows())->toBe(60000)
        ->and(targetRows($this->targetPath, 'events')[59999])->toBe(['id' => 60000, 'label' => 'event 60000']);
});

it('copies through PHP the rows of a table the database cannot copy at once, skipping only the refused ones', function () {
    config(['database.connections.clone_target' => ['driver' => 'sqlite', 'database' => $this->targetPath]]);
    DB::connection('clone_target')->statement("create table users (id integer primary key, email varchar(255) not null, name varchar(255) not null check (name <> 'Alan'))");
    DB::purge('clone_target');

    writeCloningYaml($this->workingDirectory, ['users' => keepColumns('id', 'email', 'name')]);

    $result = CloningResult::for(app(CloningRunnerHelper::class)->cloneNow($this->runName));

    expect(array_column(targetRows($this->targetPath, 'users'), 'name'))->toBe(['Ada', 'Grace'])
        ->and($result->getEntriesByEvent('direct_copy_failed'))->toHaveCount(1)
        ->and($result->getEntriesByEvent('row_skipped')[0]['pk'] ?? null)->toBe(['id' => 2]);
});

it('reads by their primary key, even a composite one, the tables without a rowid', function () {
    config(['database.connections.clone_source' => ['driver' => 'sqlite', 'database' => $this->sourcePath]]);
    $source = DB::connection('clone_source');
    $source->statement('create table tags (code text primary key, label text not null) without rowid');
    $source->statement('create table post_tags (post_id integer not null, tag text not null, label text not null, primary key (tag, post_id)) without rowid');
    $source->table('tags')->insert(array_map(fn (int $i): array => ['code' => "t{$i}", 'label' => "Tag {$i}"], range(1, 5)));
    $source->table('post_tags')->insert(array_map(fn (int $i): array => ['post_id' => $i % 2, 'tag' => 't'.intdiv($i, 2), 'label' => "Lien {$i}"], range(1, 7)));
    DB::purge('clone_source');

    $masked = ['strategy' => 'mask', 'mask_char' => '*', 'visible_chars' => 1, 'preserve_format' => false];
    writeCloningYaml($this->workingDirectory, [
        'tags' => [...keepColumns('code'), 'label' => $masked],
        'post_tags' => [...keepColumns('post_id', 'tag'), 'label' => $masked],
    ]);

    app(CloningRunnerHelper::class)->cloneNow($this->runName);

    config(['database.connections.clone_target' => ['driver' => 'sqlite', 'database' => $this->targetPath]]);
    expect(DB::connection('clone_target')->table('tags')->orderBy('code')->pluck('label', 'code')->all())->toBe([
        't1' => 'T****', 't2' => 'T****', 't3' => 'T****', 't4' => 'T****', 't5' => 'T****',
    ])->and(DB::connection('clone_target')->table('post_tags')->count())->toBe(7);
});

it('recreates the tables of a SQLite source as they are declared, with their indexes', function () {
    config(['database.connections.clone_source' => ['driver' => 'sqlite', 'database' => $this->sourcePath]]);
    DB::connection('clone_source')->statement('create unique index users_email_unique on users (email)');
    $sourceSql = DB::connection('clone_source')->table('sqlite_master')->where('name', 'users')->value('sql');
    DB::purge('clone_source');

    writeCloningYaml($this->workingDirectory, ['users' => keepColumns('id', 'email', 'name')]);

    app(CloningRunnerHelper::class)->cloneNow($this->runName);

    config(['database.connections.clone_target' => ['driver' => 'sqlite', 'database' => $this->targetPath]]);
    $target = DB::connection('clone_target');

    expect($target->table('sqlite_master')->where('name', 'users')->value('sql'))->toBe($sourceSql)
        ->and($target->table('sqlite_master')->where('type', 'index')->pluck('name')->all())->toBe(['users_email_unique']);
});

it('keeps every digit of the floating point values of a table it copies through PHP', function () {
    config(['database.connections.clone_source' => ['driver' => 'sqlite', 'database' => $this->sourcePath]]);
    DB::connection('clone_source')->statement('create table measures (id integer primary key, value double not null, label varchar(255) not null)');
    // En SQL : le query builder de Laravel enverrait M_PI arrondi aux 14 chiffres du réglage « precision » de PHP.
    DB::connection('clone_source')->statement("insert into measures values (1, 3.141592653589793, 'pi'), (2, 1.0E-20, 'tiny')");
    DB::purge('clone_source');

    writeCloningYaml($this->workingDirectory, ['measures' => [...keepColumns('id', 'value'), 'label' => ['strategy' => 'static', 'value' => 'masqué']]]);

    app(CloningRunnerHelper::class)->cloneNow($this->runName);

    expect(array_column(targetRows($this->targetPath, 'measures'), 'value'))->toBe([M_PI, 1.0E-20]);
});
