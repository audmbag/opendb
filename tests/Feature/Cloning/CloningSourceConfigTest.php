<?php

use App\Services\Cloning\CloningAnonymizationConfig;
use App\Services\Cloning\CloningSourceConfig;
use App\Services\YamlWriter;
use Symfony\Component\Yaml\Yaml;

function sourceConfigMatchers(): array
{
    return [
        'groups' => [
            'contact' => [
                'name' => 'Contact',
                'matchers' => [
                    'email' => [
                        'name' => 'Email',
                        'sensitivity' => 'high',
                        'patterns' => ['*email*'],
                        'transformation' => ['strategy' => 'fake', 'faker_method' => 'safeEmail', 'faker_arguments' => []],
                    ],
                ],
            ],
        ],
    ];
}

function sourceConfigTable(string $name, array $columns, array $primaryKey = ['id'], array $foreignKeys = []): array
{
    return [
        'name' => $name,
        'columns' => array_map(fn (string $type, string $column): array => ['name' => $column, 'type' => $type, 'nullable' => true], $columns, array_keys($columns)),
        'primary_key' => $primaryKey,
        'indexes' => [],
        'foreign_keys' => $foreignKeys,
    ];
}

/**
 * Même écriture que CloningManager, puis relecture comme le fait clonio.
 */
function sourceConfigRoundTrip(array $raw): array
{
    $writer = new YamlWriter(indent: 2, inline: fn (array $path) => end($path) === 'foreign_keys' || end($path) === 'faker_arguments');

    return Yaml::parse($writer->dump($raw));
}

it('lists every column, applies PII matchers and keeps the others', function () {
    $raw = (new CloningSourceConfig([sourceConfigTable('users', ['id' => 'varchar(36)', 'email' => 'varchar(255)', 'first_name' => 'varchar(255)'])], sourceConfigMatchers(), 'fr_FR'))->toRaw();

    expect($raw['options']['faker_locale'])->toBe('fr_FR')
        ->and($raw['tables']['users'])->toBe([
            'rows' => ['strategy' => 'full', 'clear' => 'delete'],
            'columns' => [
                'id' => ['strategy' => 'keep'],
                'email' => ['strategy' => 'fake', 'faker_method' => 'safeEmail', 'faker_arguments' => []],
                'first_name' => ['strategy' => 'keep'],
            ],
        ]);
});

it('remaps single integer primary keys with the foreign keys that reference them', function () {
    $schema = [
        sourceConfigTable('users', ['id' => 'bigint unsigned']),
        sourceConfigTable('posts', ['id' => 'int(11)', 'user_id' => 'bigint unsigned', 'parent_id' => 'int(11)'], foreignKeys: [
            ['columns' => ['user_id'], 'references_table' => 'users', 'references_columns' => ['id']],
            ['columns' => ['parent_id'], 'references_table' => 'posts', 'references_columns' => ['id']],
        ]),
        sourceConfigTable('tags', ['slug' => 'varchar(50)'], ['slug']),
        sourceConfigTable('post_tag', ['post_id' => 'int(11)', 'tag_slug' => 'varchar(50)'], ['post_id', 'tag_slug'], [
            ['columns' => ['post_id'], 'references_table' => 'posts', 'references_columns' => ['id']],
            ['columns' => ['tag_slug'], 'references_table' => 'tags', 'references_columns' => ['slug']],
        ]),
    ];

    $tables = (new CloningSourceConfig($schema, [], 'en_US'))->toRaw()['tables'];

    expect($tables['users']['columns']['id'])->toBe([
        'strategy' => 'remapping',
        'arguments' => [
            ['use' => 'random_integer'],
            ['foreign_keys' => [['table' => 'posts', 'column' => 'user_id', 'self_referential' => false]]],
        ],
    ])
        ->and($tables['posts']['columns']['id']['arguments'][1]['foreign_keys'])->toBe([
            ['table' => 'posts', 'column' => 'parent_id', 'self_referential' => true],
            ['table' => 'post_tag', 'column' => 'post_id', 'self_referential' => false],
        ])
        ->and($tables['tags']['columns']['slug'])->toBe(['strategy' => 'keep'])
        ->and($tables['post_tag']['columns']['post_id'])->toBe(['strategy' => 'keep']);
});

it('does not remap integer-looking types that are not integers', function (string $type) {
    $tables = (new CloningSourceConfig([sourceConfigTable('shapes', ['id' => $type])], [], 'en_US'))->toRaw()['tables'];

    expect($tables['shapes']['columns']['id'])->toBe(['strategy' => 'keep']);
})->with(['point', 'interval', 'tinytext', 'varchar(36)', 'int8', 'serial']);

it('skips a table whose columns are unknown rather than copying it without anonymisation', function () {
    $tables = (new CloningSourceConfig([sourceConfigTable('1.5e3', [], [])], [], 'en_US'))->toRaw()['tables'];

    expect($tables['1.5e3'])->toBe(['rows' => ['strategy' => 'skip']]);
});

it('writes table and column names that are read back unchanged', function () {
    $names = ['123', '1.5e3', 'true', 'false', 'null', 'yes', 'on', '~', '.inf', '0x1F', '2024-01-01', 'User Accounts', 'données_clients', '顧客', 'key: value', 'a # comment', "it's", 'say "hi"', '{braces}', '[brackets]', '*star', '&anchor', '!tag', '- dash', '? question', '| pipe', '> fold'];

    $schema = array_map(fn (string $name): array => sourceConfigTable($name, ['id' => 'bigint unsigned'] + array_fill_keys($names, 'varchar(50)')), $names);

    $parsed = sourceConfigRoundTrip((new CloningSourceConfig($schema, [], 'en_US'))->toRaw());

    expect(array_map('strval', array_keys($parsed['tables'])))->toBe($names);

    foreach ($parsed['tables'] as $name => $table) {
        expect(array_map('strval', array_keys($table['columns'] ?? [])))->toBe(str_contains((string) $name, '.') ? [] : ['id', ...$names]);
    }
});

it('quotes the foreign keys of numeric tables so they stay strings', function () {
    $schema = [
        sourceConfigTable('123', ['id' => 'bigint unsigned']),
        sourceConfigTable('true', ['id' => 'bigint unsigned', '123_id' => 'bigint unsigned'], foreignKeys: [
            ['columns' => ['123_id'], 'references_table' => '123', 'references_columns' => ['id']],
        ]),
    ];

    $parsed = sourceConfigRoundTrip((new CloningSourceConfig($schema, [], 'en_US'))->toRaw());

    expect($parsed['tables'][123]['columns']['id']['arguments'][1]['foreign_keys'])->toBe([
        ['table' => 'true', 'column' => '123_id', 'self_referential' => false],
    ]);
});

it('builds the client payload of numeric table and column names', function () {
    $schema = [sourceConfigTable('123', ['id' => 'bigint unsigned', '456' => 'varchar(50)', 'email' => 'varchar(255)'])];

    $raw = sourceConfigRoundTrip((new CloningSourceConfig($schema, sourceConfigMatchers(), 'en_US'))->toRaw());
    $config = new CloningAnonymizationConfig($raw, $schema, sourceConfigMatchers());

    expect($config->getTables()[0]->name)->toBe('123')
        ->and(array_column($config->toClientPayload()['tables'][0]['columns'], 'name'))->toBe(['id', '456', 'email'])
        ->and(sourceConfigRoundTrip($config->parser(['123' => ['456' => ['strategy' => 'null']]]))['tables'][123]['columns'][456])->toBe(['strategy' => 'null']);
});

it('does not remap a primary key that clonio could only remap by scanning its whole id range', function (string $type, string $max, int $count) {
    $tables = (new CloningSourceConfig([sourceConfigTable('extreme_values', ['id' => $type])], [], 'en_US', ['extreme_values' => ['max' => $max, 'count' => $count]]))->toRaw()['tables'];

    expect($tables['extreme_values']['columns']['id'])->toBe(['strategy' => 'keep']);
})->with([
    'unsigned bigint beyond PHP_INT_MAX' => ['bigint unsigned', '18446744073709551615', 20000],
    'bigint at PHP_INT_MAX' => ['bigint', (string) PHP_INT_MAX, 1],
    'unsigned int at its ceiling' => ['int unsigned', '4294967295', 20000],
    'unsupported integer type' => ['int8', '10', 10],
]);

it('remaps a primary key whose new ids fit above or below its maximum', function (string $type, ?string $max, int $count) {
    $tables = (new CloningSourceConfig([sourceConfigTable('users', ['id' => $type])], [], 'en_US', ['users' => ['max' => $max, 'count' => $count]]))->toRaw()['tables'];

    expect($tables['users']['columns']['id']['strategy'])->toBe('remapping');
})->with([
    'room above the maximum' => ['bigint unsigned', '30000', 30000],
    'empty table' => ['bigint unsigned', null, 0],
    'full tinyint, gaps scanned below its maximum' => ['tinyint unsigned', '255', 200],
]);

it('sizes the chunks after the heaviest rows', function (?int $largestRowBytes, int $chunkSize) {
    $raw = (new CloningSourceConfig([], [], 'en_US', largestRowBytes: $largestRowBytes))->toRaw();

    expect($raw['options']['chunk_size'])->toBe($chunkSize);
})->with([
    'unknown row size' => [null, 1000],
    'small rows' => [200, 1000],
    'documents of 2.3 MB' => [2_400_000, 6],
    'rows larger than the budget' => [64 * 1024 * 1024, 1],
]);

it('skips a table whose name contains a dot, which clonio cannot write to', function () {
    $schema = [
        sourceConfigTable('users', ['id' => 'bigint unsigned']),
        sourceConfigTable('1.5e3', ['id' => 'bigint unsigned', 'email' => 'varchar(191)', 'user_id' => 'bigint unsigned'], foreignKeys: [
            ['columns' => ['user_id'], 'references_table' => 'users', 'references_columns' => ['id']],
        ]),
    ];

    $tables = (new CloningSourceConfig($schema, sourceConfigMatchers(), 'en_US'))->toRaw()['tables'];

    expect($tables['1.5e3'])->toBe(['rows' => ['strategy' => 'skip']])
        ->and($tables['users']['columns']['id']['arguments'][1]['foreign_keys'])->toBe([]);
});

it('explains why a table will be skipped', function (string $name, array $table, ?string $reason) {
    expect(CloningSourceConfig::skipReason($name, $table))->toBe($reason === null ? null : __($reason));
})->with([
    'dotted name' => ['1.5e3', ['rows' => ['strategy' => 'skip']], 'cloning.skip_reasons.dotted_name'],
    'unreadable structure' => ['legacy', ['rows' => ['strategy' => 'skip']], 'cloning.skip_reasons.unknown_structure'],
    'copied table' => ['users', ['rows' => ['strategy' => 'full'], 'columns' => ['id' => ['strategy' => 'keep']]], null],
]);

it('sends the skip reason of each table to the client', function () {
    $schema = [
        sourceConfigTable('1.5e3', ['id' => 'bigint unsigned']),
        sourceConfigTable('users', ['id' => 'bigint unsigned']),
    ];

    $raw = sourceConfigRoundTrip((new CloningSourceConfig($schema, [], 'en_US'))->toRaw());
    $tables = collect((new CloningAnonymizationConfig($raw, $schema, []))->toClientPayload()['tables'])->keyBy('name');

    expect($tables['1.5e3'])->toMatchArray(['rowsStrategy' => 'skip', 'columns' => [], 'skipReason' => __('cloning.skip_reasons.dotted_name')])
        ->and($tables['users']['skipReason'])->toBeNull();
});

it('remaps only the primary keys that fit in the memory of clonio, personal data first', function () {
    $schema = [
        sourceConfigTable('comments', ['id' => 'integer', 'body' => 'text']),
        sourceConfigTable('likes', ['id' => 'integer']),
        sourceConfigTable('users', ['id' => 'integer', 'email' => 'varchar(255)']),
        sourceConfigTable('posts', ['id' => 'integer']),
    ];
    $ranges = [
        'comments' => ['max' => '500000', 'count' => 500_000],
        'likes' => ['max' => '120000', 'count' => 120_000],
        'users' => ['max' => '100000', 'count' => 100_000],
        'posts' => ['max' => '1000', 'count' => 1_000],
    ];

    $config = new CloningSourceConfig($schema, sourceConfigMatchers(), 'en_US', $ranges);
    $strategies = array_map(fn (array $table): string => $table['columns']['id']['strategy'], $config->toRaw()['tables']);

    expect($strategies)->toBe(['comments' => 'keep', 'likes' => 'keep', 'users' => 'remapping', 'posts' => 'remapping'])
        ->and($config->keptPrimaryKeys())->toBe(['likes' => 120_000, 'comments' => 500_000])
        ->and($config->remappingOverflow(['users', 'posts']))->toBe([])
        ->and($config->remappingOverflow(['comments', 'users', 'posts', 'likes']))->toBe(['likes', 'comments']);
});

it('drops the foreign keys of a primary key kept for lack of memory', function () {
    $schema = [
        sourceConfigTable('comments', ['id' => 'integer', 'parent_id' => 'integer'], foreignKeys: [
            ['columns' => ['parent_id'], 'references_table' => 'comments', 'references_columns' => ['id']],
        ]),
    ];

    $tables = (new CloningSourceConfig($schema, [], 'en_US', ['comments' => ['max' => '500000', 'count' => 500_000]]))->toRaw()['tables'];

    expect($tables['comments']['columns'])->toBe(['id' => ['strategy' => 'keep'], 'parent_id' => ['strategy' => 'keep']]);
});

it('tells the client which tables keep their identifiers', function () {
    app()->setLocale('fr');
    $schema = [sourceConfigTable('comments', ['id' => 'integer']), sourceConfigTable('posts', ['id' => 'integer'])];
    $config = new CloningSourceConfig($schema, [], 'fr_FR', ['comments' => ['max' => '500000', 'count' => 500_000], 'posts' => ['max' => '10', 'count' => 10]]);

    $tables = collect((new CloningAnonymizationConfig(sourceConfigRoundTrip($config->toRaw()), $schema, [], $config->keptPrimaryKeys()))->toClientPayload()['tables'])->keyBy('name');

    expect($tables['comments']['remappingNotice'])->toBe(__('cloning.remapping_notices.too_many_rows', ['rows' => "500\u{202F}000"]))
        ->and($tables['comments']['remappingNotice'])->toContain('500')
        ->and($tables['posts']['remappingNotice'])->toBeNull();
});
