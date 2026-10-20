<?php

use App\Enums\OperatingSystemEnum;
use App\Http\Requests\Project\SaveWithCloningRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\Cloning\CloningManager;
use App\Services\SystemInfoService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-cloning-');
    mkdir($this->home);

    $this->app->instance(SystemInfoService::class, new SystemInfoService(
        OperatingSystemEnum::current(),
        'OpenDB',
        ['HOME' => $this->home, 'USERPROFILE' => $this->home],
    ));

    $this->cloningId = '2026-10-13_14-30-00_a1B2c3D4';
    app(SystemInfoService::class)
        ->temporaryDirectory($this->cloningId)
        ->put(CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml', 'version: 1');

    $this->user = User::factory()->create();
    $this->project = $this->user->projects()->create(['name' => 'Projet source', 'path' => $this->home]);
    $this->source = $this->project->driver_config()->create([
        'driver_id' => 'sqlite',
        'database' => $this->home.'/source.sqlite',
    ]);

    $this->payload = [
        'cloningDriverConfig' => [
            'driver_id' => 'sqlite',
            'database' => $this->home.'/target.sqlite',
            'from_id' => (string) $this->source->id,
            'create_database_if_not_exists' => true,
        ],
        'anonymisation' => [
            'cloningId' => $this->cloningId,
            'tables' => [
                'users' => [
                    'id' => [
                        'strategy' => 'remapping',
                        'remappingUse' => 'random_integer',
                        'foreignKeys' => [['table' => 'posts', 'column' => 'user_id', 'selfReferential' => false]],
                    ],
                    'name' => ['strategy' => 'fake', 'fakerMethod' => 'name', 'fakerArguments' => []],
                    'reference' => ['strategy' => 'fake', 'fakerMethod' => 'bothify', 'fakerArguments' => [' ## ?? ']],
                    'email' => ['strategy' => 'hash', 'algorithm' => 'sha256'],
                    'phone' => ['strategy' => 'mask', 'visibleChars' => 4, 'maskChar' => '*', 'preserveFormat' => true],
                    'bio' => ['strategy' => 'null'],
                    'code' => ['strategy' => 'static', 'value' => ''],
                    'created_at' => ['strategy' => 'keep'],
                ],
                'public.logs' => [
                    'ip.v4' => ['strategy' => 'static', 'value' => '0.0.0.0'],
                ],
                'migrations' => [],
            ],
        ],
        'options' => [
            'enforce_column_types' => true,
            'drop_unknown_tables' => false,
            'drop_extra_columns' => false,
            'disable_fk_checks' => true,
            'break_on_failure' => false,
        ],
        'agentDisabled' => ['sessions'],
        'cloningDisabled' => [],
    ];

    Route::post('/_test/projects/{project}/save-with-cloning', fn (Project $project, SaveWithCloningRequest $request) => response()->json($request->validated()))
        ->middleware(['web', 'auth']);

    $this->submit = fn (array $payload) => $this->actingAs($this->user)
        ->postJson("/_test/projects/{$this->project->id}/save-with-cloning", $payload);
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

it('hands every validated value to the controller untouched', function () {
    ($this->submit)($this->payload)
        ->assertOk()
        ->assertExactJson($this->payload);
});

it('drops the fields that do not belong to the request or to the chosen strategy', function () {
    $payload = $this->payload;
    $payload['unexpected'] = 'value';
    $payload['options']['faker_locale'] = 'fr_FR';
    $payload['anonymisation']['tables']['users']['created_at'] = ['strategy' => 'keep', 'value' => 'ignored', 'algorithm' => 'crc32'];

    ($this->submit)($payload)
        ->assertOk()
        ->assertExactJson($this->payload);
});

it('accepts table and column names containing an asterisk without applying their rules to other columns', function () {
    $payload = $this->payload;
    $payload['anonymisation']['tables']['yaml_hostile_columns'] = [
        '*star' => ['strategy' => 'fake', 'fakerMethod' => 'name', 'fakerArguments' => []],
        'polestar' => ['strategy' => 'keep'],
        '*' => ['strategy' => 'null'],
    ];
    $payload['anonymisation']['tables']['*'] = ['id' => ['strategy' => 'keep']];

    ($this->submit)($payload)
        ->assertOk()
        ->assertExactJson($payload);
});

it('casts a missing create option to false', function () {
    touch($this->home.'/target.sqlite');

    $payload = $this->payload;
    unset($payload['cloningDriverConfig']['create_database_if_not_exists']);

    ($this->submit)($payload)
        ->assertOk()
        ->assertJsonPath('cloningDriverConfig.create_database_if_not_exists', false);
});

it('rejects an invalid value', function (string $path, mixed $value, string $error) {
    $payload = $this->payload;
    data_set($payload, $path, $value);

    ($this->submit)($payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$error]);
})->with([
    'unknown driver' => ['cloningDriverConfig.driver_id', 'oracle', 'cloningDriverConfig.driver_id'],
    'server driver without host' => ['cloningDriverConfig.driver_id', 'mysql', 'cloningDriverConfig.host'],
    'unwritable sqlite target' => ['cloningDriverConfig.database', '/missing-directory/target.sqlite', 'cloningDriverConfig.database'],
    'cloning id escaping the temporary directory' => ['anonymisation.cloningId', '../../etc', 'anonymisation.cloningId'],
    'unknown cloning session' => ['anonymisation.cloningId', '2026-01-01_00-00-00_zzzzzzzz', 'anonymisation.cloningId'],
    'tables that are not a map' => ['anonymisation.tables', 'users', 'anonymisation.tables'],
    'table that is not a map' => ['anonymisation.tables.users', 'id', 'anonymisation.tables.users'],
    'column that is not a map' => ['anonymisation.tables.users.email', 'hash', 'anonymisation.tables.users.email'],
    'invalid column of a table with an asterisk' => ['anonymisation.tables', ['us*ers' => ['*' => ['strategy' => 'encrypt']]], 'anonymisation.tables.us*ers.*.strategy'],
    'invalid column of a dotted table' => ['anonymisation.tables', ['public.logs' => ['ip.v4' => ['strategy' => 'hash', 'algorithm' => 'crc32']]], 'anonymisation.tables.public.logs.ip.v4.algorithm'],
    'unknown strategy' => ['anonymisation.tables.users.email.strategy', 'encrypt', 'anonymisation.tables.users.email.strategy'],
    'unknown faker method' => ['anonymisation.tables.users.name.fakerMethod', 'creditScore', 'anonymisation.tables.users.name.fakerMethod'],
    'faker arguments that are not a list' => ['anonymisation.tables.users.name.fakerArguments', ['min' => 1], 'anonymisation.tables.users.name.fakerArguments'],
    'unknown hash algorithm' => ['anonymisation.tables.users.email.algorithm', 'crc32', 'anonymisation.tables.users.email.algorithm'],
    'negative visible chars' => ['anonymisation.tables.users.phone.visibleChars', -1, 'anonymisation.tables.users.phone.visibleChars'],
    'mask made of several chars' => ['anonymisation.tables.users.phone.maskChar', '**', 'anonymisation.tables.users.phone.maskChar'],
    'non boolean preserve format' => ['anonymisation.tables.users.phone.preserveFormat', 'yes', 'anonymisation.tables.users.phone.preserveFormat'],
    'non scalar static value' => ['anonymisation.tables.users.code.value', ['a' => 'b'], 'anonymisation.tables.users.code.value'],
    'foreign key without column' => ['anonymisation.tables.users.id.foreignKeys', [['table' => 'posts', 'selfReferential' => false]], 'anonymisation.tables.users.id.foreignKeys.0.column'],
    'non boolean option' => ['options.enforce_column_types', 'yes', 'options.enforce_column_types'],
    'non boolean break on failure' => ['options.break_on_failure', 'yes', 'options.break_on_failure'],
    'non string disabled table' => ['agentDisabled', [['users']], 'agentDisabled.0'],
    'duplicated ignored table' => ['cloningDisabled', ['users', 'users'], 'cloningDisabled.0'],
]);

it('rejects a missing value', function (string $path) {
    $payload = $this->payload;
    Arr::forget($payload, $path);

    ($this->submit)($payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$path]);
})->with([
    'cloningDriverConfig',
    'cloningDriverConfig.database',
    'cloningDriverConfig.from_id',
    'anonymisation',
    'anonymisation.cloningId',
    'anonymisation.tables',
    'anonymisation.tables.users.email.strategy',
    'anonymisation.tables.users.name.fakerMethod',
    'anonymisation.tables.users.email.algorithm',
    'anonymisation.tables.users.phone.maskChar',
    'anonymisation.tables.users.code.value',
    'anonymisation.tables.users.id.foreignKeys',
    'options',
    'options.drop_unknown_tables',
    'options.disable_fk_checks',
    'options.break_on_failure',
    'agentDisabled',
    'cloningDisabled',
]);

it('rejects a source connection that belongs to another project', function () {
    $other = $this->user->projects()->create(['name' => 'Autre projet', 'path' => $this->home]);
    $otherSource = $other->driver_config()->create(['driver_id' => 'sqlite', 'database' => $this->home.'/other.sqlite']);

    $payload = $this->payload;
    $payload['cloningDriverConfig']['from_id'] = (string) $otherSource->id;

    ($this->submit)($payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cloningDriverConfig.from_id']);
});

it('rejects a target that is the source database', function () {
    touch($this->home.'/source.sqlite');

    $payload = $this->payload;
    $payload['cloningDriverConfig']['database'] = $this->home.'/source.sqlite';

    ($this->submit)($payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cloningDriverConfig.database']);
});

it('validates the payload on the save with cloning endpoint', function () {
    $this->actingAs($this->user)
        ->postJson("/projects/{$this->project->id}/save-with-cloning", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cloningDriverConfig', 'anonymisation', 'options', 'agentDisabled', 'cloningDisabled']);
});
