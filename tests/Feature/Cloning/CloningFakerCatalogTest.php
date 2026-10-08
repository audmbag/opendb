<?php

use App\Services\Cloning\Faker\CloningFakerCatalog;
use App\Services\Cloning\Pii\PiiMatcherCatalog;
use App\Services\Cloning\Pii\PiiMatcherDefinition;
use App\Services\Cloning\Pii\PiiMatcherMatch;
use App\Services\Cloning\Schema\DbColumnInfo;
use Symfony\Component\Yaml\Yaml;

function fakerColumn(string $type): DbColumnInfo
{
    return new DbColumnInfo($type, nullable: true, primaryKey: false, compositePrimaryKey: false, foreignKey: false, unique: false);
}

function fakerSuggestion(array $transformation, ?string $type = null): ?array
{
    $matcher = new PiiMatcherDefinition('email_address', 'contact', 'Contact', 'Email', 'high', true, ['*'], $transformation);

    return CloningFakerCatalog::suggestionFor($type === null ? null : fakerColumn($type), new PiiMatcherMatch($matcher, '*'));
}

it('tells whether clonio accepts a faker method', function (string $method, bool $expected) {
    expect(CloningFakerCatalog::supports($method))->toBe($expected);
})->with([
    'described method' => ['safeEmail', true],
    'known method without description' => ['ipv6', true],
    'last method of the list' => ['isbn13', true],
    'faker method unknown to clonio' => ['creditCardExpirationDateString', false],
    'random digit' => ['randomDigit', false],
    'non-zero random digit' => ['randomDigitNotNull', false],
    'not a faker method' => ['success', false],
    'wrong case' => ['SafeEmail', false],
    'empty' => ['', false],
]);

it('only offers methods accepted by clonio', function (string $type) {
    $methods = array_column(CloningFakerCatalog::catalogFor(fakerColumn($type)), 'fakerMethod');

    expect($methods)->not->toBeEmpty()
        ->each(fn ($method) => $method->toBeIn(CloningFakerCatalog::methods()));
})->with(['varchar(255)', 'varchar(20)', 'longtext', 'char(36)', 'int unsigned', 'bigint', 'float', 'decimal(8,3)', 'tinyint(1)', 'datetime', 'date', 'time', 'year']);

it('offers number generators for an integer column', function () {
    $methods = array_column(CloningFakerCatalog::catalogFor(fakerColumn('int')), 'fakerMethod');

    expect($methods)->toBe(['numberBetween', 'randomNumber']);
});

it('suggests a described method with its parameters', function () {
    $entry = fakerSuggestion(['strategy' => 'fake', 'faker_method' => 'numberBetween', 'faker_arguments' => [18, 90]], 'int');

    expect($entry)->toMatchArray(['fakerMethod' => 'numberBetween', 'fakerArguments' => [18, 90], 'group' => 'number'])
        ->and(array_column($entry['parameters'], 'name'))->toBe(['min', 'max']);
});

it('suggests a method accepted by clonio even without a description', function (string $method) {
    $entry = fakerSuggestion(['strategy' => 'fake', 'faker_method' => $method, 'faker_arguments' => ['a']], 'varchar(255)');

    expect($entry)->toBe(['fakerMethod' => $method, 'fakerArguments' => ['a'], 'group' => 'other', 'parameters' => []]);
})->with(['email', 'ipv6', 'dateTimeBetween', 'randomElement', 'stateAbbr']);

it('rejects a suggestion whose method clonio does not accept', function (array $transformation) {
    expect(fakerSuggestion($transformation, 'varchar(255)'))->toBeNull();
})->with([
    'faker method unknown to clonio' => [['strategy' => 'fake', 'faker_method' => 'creditCardExpirationDateString']],
    'random digit' => [['strategy' => 'fake', 'faker_method' => 'randomDigit']],
    'not a faker method' => [['strategy' => 'fake', 'faker_method' => 'success']],
    'missing method' => [['strategy' => 'fake']],
    'other strategy' => [['strategy' => 'static', 'value' => 'REDACTED']],
]);

it('ships pii matchers that clonio accepts', function () {
    $raw = Yaml::parseFile(resource_path('app/clonio.pii-matchers.yaml'));
    $fakes = 0;

    foreach ($raw['groups'] as $group) {
        foreach ($group['matchers'] as $key => $matcher) {
            $transformation = $matcher['transformation'];

            expect(trim($transformation['strategy'], '"'))->toBeIn(
                ['keep', 'fake', 'hash', 'mask', 'null', 'static', 'template', 'remapping'],
                "Strategy of [{$key}]",
            );

            if ($transformation['strategy'] === 'hash') {
                expect($transformation['algorithm'])->toBeIn(['sha256', 'sha512', 'md5', 'sha1'], "Hash algorithm of [{$key}]");
            }

            if ($transformation['strategy'] === 'fake') {
                $fakes++;

                expect(CloningFakerCatalog::supports($transformation['faker_method']))
                    ->toBeTrue("Faker method [{$transformation['faker_method']}] of [{$key}] is not accepted by clonio");
            }
        }
    }

    expect($fakes)->toBeGreaterThan(40);
});

it('suggests a formatted date string for a card expiration column', function (string $column) {
    $match = PiiMatcherCatalog::fromRaw(Yaml::parseFile(resource_path('app/clonio.pii-matchers.yaml')))->match($column);
    $entry = CloningFakerCatalog::suggestionFor(fakerColumn('date'), $match);

    expect($match?->matcher->key)->toBe('card_expiry')
        ->and($entry)->toMatchArray(['fakerMethod' => 'date', 'fakerArguments' => ['Y-m-d', '+5 years'], 'group' => 'date'])
        ->and(array_column($entry['parameters'], 'name'))->toBe(['format', 'max']);
})->with(['card_expiry', 'card_expiration_date', 'cc_exp_date']);
