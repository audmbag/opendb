<?php

use App\Services\Cloning\CloningAnonymizationConfig;
use App\Services\Cloning\Schema\DbColumnInfo;
use App\Services\Cloning\Strategy\CloningStrategyResolver;
use App\Services\Cloning\Strategy\CloningTransformation;

function nullFallbackColumn(string $type, bool $nullable = false, bool $unique = false, bool $primaryKey = false, bool $foreignKey = false): DbColumnInfo
{
    return new DbColumnInfo($type, $nullable, $primaryKey, false, $foreignKey, $unique);
}

function nullFallbackConfig(): CloningAnonymizationConfig
{
    $columns = [
        'id' => ['bigint unsigned', false],
        'session_id' => ['varchar(255)', false],
        'gender' => ['varchar(10)', true],
        'marital_status' => ['varchar(20)', false],
        'salary' => ['decimal(10,2)', false],
        'email' => ['varchar(255)', false],
    ];

    $raw = [
        'version' => 1,
        'connection' => ['driver' => 'mysql'],
        'options' => ['chunk_size' => 500],
        'tables' => [
            'users' => [
                'rows' => ['strategy' => 'full'],
                'columns' => [
                    'id' => ['strategy' => 'keep'],
                    'session_id' => ['strategy' => 'null'],
                    'gender' => ['strategy' => 'null'],
                    'marital_status' => ['strategy' => 'null'],
                    'salary' => ['strategy' => 'null'],
                    'email' => ['strategy' => 'fake', 'faker_method' => 'safeEmail', 'faker_arguments' => []],
                    'unknown_to_schema' => ['strategy' => 'null'],
                ],
            ],
        ],
    ];

    $schema = [[
        'name' => 'users',
        'primary_key' => ['id'],
        'foreign_keys' => [],
        'indexes' => [],
        'columns' => collect($columns)
            ->map(fn (array $column, string $name) => ['name' => $name, 'type' => $column[0], 'nullable' => $column[1]])
            ->values()
            ->all(),
    ]];

    $matchers = [
        'groups' => [
            'digital_identity' => [
                'name' => 'Digital Identity',
                'matchers' => [
                    'session_id' => [
                        'name' => 'Session ID',
                        'sensitivity' => 'high',
                        'patterns' => ['session_id'],
                        'transformation' => ['strategy' => 'null'],
                    ],
                    'gender' => [
                        'name' => 'Gender',
                        'sensitivity' => 'medium',
                        'patterns' => ['gender'],
                        'transformation' => ['strategy' => 'null'],
                    ],
                ],
            ],
        ],
    ];

    return new CloningAnonymizationConfig($raw, $schema, $matchers);
}

/**
 * @return array<string, array<string, mixed>>
 */
function nullFallbackColumns(): array
{
    return collect(nullFallbackConfig()->toClientPayload()['tables'][0]['columns'])->keyBy('name')->all();
}

it('replaces a null strategy on a column that does not accept null', function (string $type, bool $unique, array $expected) {
    $adapted = CloningStrategyResolver::adapt(new CloningTransformation(strategy: 'null'), nullFallbackColumn($type, unique: $unique));

    expect($adapted->toRaw())->toBe($expected);
})->with([
    'string' => ['varchar(255)', false, ['strategy' => 'static', 'value' => 'REDACTED']],
    'text' => ['text', false, ['strategy' => 'static', 'value' => 'REDACTED']],
    'string of exactly 8 characters' => ['char(8)', false, ['strategy' => 'static', 'value' => 'REDACTED']],
    'string too short for the placeholder' => ['varchar(3)', false, ['strategy' => 'static', 'value' => '']],
    'unique string' => ['varchar(255)', true, ['strategy' => 'hash', 'algorithm' => 'sha256']],
    'unique string of 64 characters' => ['char(64)', true, ['strategy' => 'hash', 'algorithm' => 'sha256']],
    'unique string of 40 characters' => ['varchar(40)', true, ['strategy' => 'hash', 'algorithm' => 'sha1']],
    'unique string of 32 characters' => ['varchar(32)', true, ['strategy' => 'hash', 'algorithm' => 'md5']],
    'unique string too short for a digest' => ['varchar(20)', true, ['strategy' => 'fake', 'faker_method' => 'text', 'faker_arguments' => [20]]],
    'unique string too short for anything' => ['varchar(3)', true, ['strategy' => 'keep']],
    'uuid' => ['uuid', false, ['strategy' => 'fake', 'faker_method' => 'uuid', 'faker_arguments' => []]],
    'unique uuid' => ['uuid', true, ['strategy' => 'fake', 'faker_method' => 'uuid', 'faker_arguments' => []]],
    'integer' => ['int', false, ['strategy' => 'static', 'value' => 0]],
    'decimal' => ['decimal(10,2)', false, ['strategy' => 'static', 'value' => 0]],
    'unique integer' => ['int unsigned', true, ['strategy' => 'fake', 'faker_method' => 'numberBetween', 'faker_arguments' => [0, 100]]],
    'boolean' => ['tinyint(1)', false, ['strategy' => 'static', 'value' => false]],
    'datetime' => ['datetime', false, ['strategy' => 'fake', 'faker_method' => 'date', 'faker_arguments' => ['Y-m-d H:i:s', 'now']]],
    'date' => ['date', false, ['strategy' => 'fake', 'faker_method' => 'date', 'faker_arguments' => ['Y-m-d', 'now']]],
    'time' => ['time', false, ['strategy' => 'fake', 'faker_method' => 'time', 'faker_arguments' => ['H:i:s', 'now']]],
    'json' => ['json', false, ['strategy' => 'static', 'value' => '{}']],
    'binary' => ['blob', false, ['strategy' => 'keep']],
    'enum' => ["enum('M','F')", false, ['strategy' => 'keep']],
]);

it('keeps the original value of a key column that does not accept null', function (bool $primaryKey, bool $foreignKey) {
    $column = nullFallbackColumn('bigint unsigned', primaryKey: $primaryKey, foreignKey: $foreignKey);

    expect(CloningStrategyResolver::adapt(new CloningTransformation(strategy: 'null'), $column)->toRaw())->toBe(['strategy' => 'keep']);
})->with([
    'primary key' => [true, false],
    'foreign key' => [false, true],
]);

it('only suggests a strategy the column allows', function (string $type, bool $unique) {
    $column = nullFallbackColumn($type, unique: $unique);
    $adapted = CloningStrategyResolver::adapt(new CloningTransformation(strategy: 'null'), $column);

    expect($adapted->strategy)->not->toBe('null')
        ->and(CloningStrategyResolver::options($column, 'keep'))->toContain($adapted->strategy);
})->with([
    ['varchar(255)', false], ['varchar(255)', true], ['varchar(3)', false], ['varchar(3)', true], ['uuid', true],
    ['int', false], ['int', true], ['tinyint(1)', false], ['tinyint(1)', true], ['datetime', false], ['datetime', true],
    ['json', false], ['json', true], ['blob', false], ["enum('M','F')", false], ["enum('M','F')", true],
]);

it('leaves a transformation untouched when null is acceptable or not involved', function (CloningTransformation $transformation, ?DbColumnInfo $column) {
    expect(CloningStrategyResolver::adapt($transformation, $column))->toBe($transformation);
})->with([
    'nullable column' => [fn () => new CloningTransformation(strategy: 'null'), fn () => nullFallbackColumn('varchar(255)', nullable: true)],
    'column missing from the schema' => [fn () => new CloningTransformation(strategy: 'null'), null],
    'another strategy' => [fn () => new CloningTransformation(strategy: 'static', value: 'REDACTED'), fn () => nullFallbackColumn('varchar(255)')],
    'keep' => [fn () => new CloningTransformation(strategy: 'keep'), fn () => nullFallbackColumn('varchar(255)')],
]);

it('adapts the null strategies of the generated config in the client payload', function () {
    $columns = nullFallbackColumns();

    expect($columns['session_id'])->toMatchArray(['strategy' => 'static', 'value' => 'REDACTED'])
        ->and($columns['marital_status'])->toMatchArray(['strategy' => 'static', 'value' => 'REDACTED'])
        ->and($columns['salary'])->toMatchArray(['strategy' => 'static', 'value' => 0])
        ->and($columns['gender']['strategy'])->toBe('null')
        ->and($columns['unknown_to_schema']['strategy'])->toBe('null')
        ->and($columns['email']['strategy'])->toBe('fake');
});

it('no longer offers the null strategy on a column that does not accept it', function () {
    $columns = nullFallbackColumns();

    expect($columns['session_id']['options'])->toBe(['keep', 'fake', 'hash', 'mask', 'static'])
        ->and($columns['gender']['options'])->toContain('null');
});

it('adapts the transformation suggested by the detected matcher', function () {
    $columns = nullFallbackColumns();

    expect($columns['session_id']['detectedMatcher']['suggestedTransformation'])->toBe(['strategy' => 'static', 'value' => 'REDACTED'])
        ->and($columns['gender']['detectedMatcher']['suggestedTransformation'])->toBe(['strategy' => 'null']);
});

it('adapts the columns the draft leaves untouched in the final config', function () {
    $raw = nullFallbackConfig()->parser([
        'users' => [
            'marital_status' => ['strategy' => 'hash', 'algorithm' => 'sha1'],
        ],
    ]);

    expect($raw['tables']['users']['columns'])->toBe([
        'id' => ['strategy' => 'keep'],
        'session_id' => ['strategy' => 'static', 'value' => 'REDACTED'],
        'gender' => ['strategy' => 'null'],
        'marital_status' => ['strategy' => 'hash', 'algorithm' => 'sha1'],
        'salary' => ['strategy' => 'static', 'value' => 0],
        'email' => ['strategy' => 'fake', 'faker_method' => 'safeEmail', 'faker_arguments' => []],
        'unknown_to_schema' => ['strategy' => 'null'],
    ]);
});
