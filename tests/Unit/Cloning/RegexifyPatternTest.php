<?php

use App\Services\Cloning\Engine\Cloning\RegexifyPattern;
use Symfony\Component\Yaml\Yaml;

it('draws values matching each regexify pattern of the PII matchers', function () {
    $patterns = collect(Yaml::parseFile(dirname(__DIR__, 3).'/resources/app/clonio.pii-matchers.yaml')['groups'])
        ->flatMap(fn (array $group): array => $group['matchers'])
        ->filter(fn (mixed $matcher): bool => is_array($matcher) && ($matcher['transformation']['faker_method'] ?? null) === 'regexify')
        ->map(fn (array $matcher): string => $matcher['transformation']['faker_arguments'][0])
        ->values();

    expect($patterns)->not->toBeEmpty();

    foreach ($patterns as $pattern) {
        $compiled = RegexifyPattern::compile($pattern);

        expect($compiled)->not->toBeNull();

        foreach (range(1, 20) as $draw) {
            expect($compiled->generate())->toMatch('/^'.$pattern.'$/');
        }
    }
});

it('draws optional parts and repetition ranges', function () {
    $values = collect(range(1, 200))->map(fn (): string => RegexifyPattern::compile('^ab?[0-9]{1,3}\.x$')->generate());

    expect($values->every(fn (string $value): bool => preg_match('/^ab?[0-9]{1,3}\.x$/', $value) === 1))->toBeTrue()
        ->and($values->map(fn (string $value): int => strlen($value))->unique()->sort()->values()->all())->toBe([4, 5, 6, 7]);
});

it('leaves to Faker the patterns it cannot draw', function (string $pattern) {
    expect(RegexifyPattern::compile($pattern))->toBeNull();
})->with(['(foo|bar)', 'a.c', 'a+', 'a*', '[^a]{2,1}', 'x{2,1}', '\b']);
