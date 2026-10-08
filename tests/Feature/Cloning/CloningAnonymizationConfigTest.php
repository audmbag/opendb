<?php

use App\Services\Cloning\CloningAnonymizationConfig;
use App\Services\Cloning\Strategy\CloningTransformation;

function cloningConfig(): CloningAnonymizationConfig
{
    $raw = [
        'version' => 1,
        'connection' => ['driver' => 'mysql'],
        'options' => ['chunk_size' => 500],
        'tables' => [
            'users' => [
                'rows' => ['strategy' => 'all'],
                'columns' => [
                    'id' => ['strategy' => 'keep'],
                    'email' => ['strategy' => 'fake', 'faker_method' => 'safeEmail', 'faker_arguments' => []],
                    'first_name' => ['strategy' => 'keep'],
                ],
            ],
        ],
    ];

    $schema = [
        [
            'name' => 'users',
            'primary_key' => ['id'],
            'foreign_keys' => [],
            'indexes' => [
                ['unique' => true, 'columns' => ['email']],
            ],
            'columns' => [
                ['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false],
                ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => false],
                ['name' => 'first_name', 'type' => 'varchar(255)', 'nullable' => true],
            ],
        ],
    ];

    $matchers = [
        'groups' => [
            'contact' => [
                'name' => 'Contact',
                'matchers' => [
                    'email' => [
                        'name' => 'Email',
                        'sensitivity' => 'high',
                        'patterns' => ['*email*'],
                        'transformation' => ['strategy' => 'fake', 'faker_method' => 'safeEmail'],
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
function cloningColumns(array $payload): array
{
    return collect($payload['tables'][0]['columns'])->keyBy('name')->all();
}

it('builds the client payload from the config, the schema and the pii matchers', function () {
    $payload = cloningConfig()->toClientPayload();
    $columns = cloningColumns($payload);

    expect($payload['tables'])->toHaveCount(1)
        ->and($payload['tables'][0]['name'])->toBe('users')
        ->and($payload['tables'][0]['rowsStrategy'])->toBe('all')
        ->and($payload['labels']['strategies'])->toHaveKey('keep')
        ->and($columns['email']['type'])->toBe('varchar(255)')
        ->and($columns['email']['strategy'])->toBe('fake')
        ->and($columns['email']['fakerMethod'])->toBe('safeEmail');
});

it('restricts the available strategies to what the column allows', function () {
    $columns = cloningColumns(cloningConfig()->toClientPayload());

    expect($columns['id']['options'])->toBe(['keep', 'remapping'])
        ->and($columns['email']['options'])->toBe(['keep', 'fake', 'hash', 'mask'])
        ->and($columns['first_name']['options'])->toBe(['keep', 'fake', 'hash', 'mask', 'null', 'static']);
});

it('detects pii columns and suggests the matcher transformation', function () {
    $columns = cloningColumns(cloningConfig()->toClientPayload());

    expect($columns['first_name']['detectedMatcher'])->toBeNull()
        ->and($columns['email']['detectedMatcher'])->toMatchArray([
            'key' => 'email',
            'group' => 'contact',
            'sensitivity' => 'high',
            'matchedPattern' => '*email*',
            'suggestedTransformation' => ['strategy' => 'fake', 'fakerMethod' => 'safeEmail', 'fakerArguments' => []],
        ])
        ->and($columns['email']['suggestedFakerMethod']['fakerMethod'])->toBe('safeEmail');
});

it('shares one faker catalog between columns of the same type', function () {
    $payload = cloningConfig()->toClientPayload();
    $columns = cloningColumns($payload);

    expect($columns['id']['fakerCatalogKey'])->toBeNull()
        ->and($columns['email']['fakerCatalogKey'])->toBe($columns['first_name']['fakerCatalogKey'])
        ->and($payload['fakerCatalogs'])->toHaveCount(1)
        ->and($payload['fakerCatalogs'])->toHaveKey($columns['email']['fakerCatalogKey']);
});

it('merges a draft back into the raw config', function () {
    $raw = cloningConfig()->parser([
        'users' => [
            'email' => ['strategy' => 'mask', 'visibleChars' => 2, 'maskChar' => '*', 'preserveFormat' => true],
            'first_name' => new CloningTransformation(strategy: 'null'),
        ],
    ]);

    expect($raw['version'])->toBe(1)
        ->and($raw['tables']['users']['rows'])->toBe(['strategy' => 'all'])
        ->and($raw['tables']['users']['columns'])->toBe([
            'id' => ['strategy' => 'keep'],
            'email' => ['strategy' => 'mask', 'visible_chars' => 2, 'mask_char' => '*', 'preserve_format' => true],
            'first_name' => ['strategy' => 'null'],
        ]);
});

it('rejects a draft that targets an unknown table', function () {
    cloningConfig()->parser(['orders' => ['id' => ['strategy' => 'keep']]]);
})->throws(InvalidArgumentException::class);
