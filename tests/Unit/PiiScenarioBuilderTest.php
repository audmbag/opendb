<?php

require_once dirname(__DIR__, 2).'/database/pii-scenarios/PiiScenarioBuilder.php';
require_once dirname(__DIR__, 2).'/database/pii-scenarios/PiiSql.php';

/**
 * @return array<int, array{title: string, build: Closure}>
 */
function piiScenarios(): array
{
    $scenarios = [];
    foreach (glob(dirname(__DIR__, 2).'/database/pii-scenarios/scenarios/scenario_*.php') as $file) {
        $scenarios[(int) preg_replace('/\D/', '', basename($file))] = require $file;
    }
    ksort($scenarios);

    return $scenarios;
}

it('refuses to act on a database outside the reserved prefix', function (string $sql) {
    (new PiiScenarioBuilder(null))->run($sql);
})->with([
    'drop' => ['DROP DATABASE opendb'],
    'drop if exists' => ['DROP DATABASE IF EXISTS `production`'],
    'drop schema' => ['drop schema mysql'],
    'create' => ['CREATE DATABASE IF NOT EXISTS `pii_test`'],
    'use' => ['USE mysql'],
    'lookalike prefix' => ['DROP DATABASE `pii_test_scenario_1_backup`'],
    'prefix without number' => ['DROP DATABASE `pii_test_scenario_`'],
])->throws(LogicException::class);

it('accepts the reserved databases and plain table statements', function (string $sql) {
    $builder = new PiiScenarioBuilder(null);
    $builder->run($sql);

    expect($builder->statements)->toBe([$sql]);
})->with([
    'DROP DATABASE IF EXISTS `pii_test_scenario_3`',
    'CREATE DATABASE `pii_test_scenario_12` CHARACTER SET utf8mb4',
    'USE `pii_test_scenario_1`',
    'CREATE TABLE users (id INT)',
    'DROP TABLE IF EXISTS users',
]);

it('recreates only the numbered scenario database', function () {
    $builder = new PiiScenarioBuilder(null);
    $builder->recreateDatabase(4);

    expect($builder->statements[0])->toBe('DROP DATABASE IF EXISTS `pii_test_scenario_4`')
        ->and($builder->statements[1])->toStartWith('CREATE DATABASE `pii_test_scenario_4`')
        ->and($builder->statements[2])->toBe('USE `pii_test_scenario_4`');
});

it('fills a table in chunks covering every row exactly once', function () {
    $builder = new PiiScenarioBuilder(null);
    $builder->fill('users', 250, ['email' => "CONCAT('u', n)"], 100);

    expect($builder->statements)->toHaveCount(3)
        ->and($builder->statements[0])->toContain('INSERT INTO `users` (`email`)')->toContain('SELECT 1 UNION ALL SELECT n + 1 FROM seq WHERE n < 100)')
        ->and($builder->statements[1])->toContain('SELECT 101 UNION ALL SELECT n + 1 FROM seq WHERE n < 200)')
        ->and($builder->statements[2])->toContain('SELECT 201 UNION ALL SELECT n + 1 FROM seq WHERE n < 250)')
        ->and($builder->insertedRows)->toBe(250);
});

it('applies the scale to row counts without going below one row', function (float $scale, int $expected) {
    expect((new PiiScenarioBuilder(null, $scale))->rows(1000))->toBe($expected);
})->with([
    'full' => [1.0, 1000],
    'tenth' => [0.1, 100],
    'double' => [2.0, 2000],
    'tiny' => [0.00001, 1],
]);

it('escapes backticks in identifiers', function () {
    expect(PiiScenarioBuilder::quote('back`tick'))->toBe('`back``tick`')
        ->and(PiiScenarioBuilder::quote('User Accounts'))->toBe('`User Accounts`');
});

it('ships seven numbered scenarios', function () {
    $scenarios = piiScenarios();

    expect(array_keys($scenarios))->toBe([1, 2, 3, 4, 5, 6, 7]);

    foreach ($scenarios as $scenario) {
        expect($scenario['title'])->toBeString()->not->toBeEmpty()
            ->and($scenario['build'])->toBeInstanceOf(Closure::class);
    }
});

it('builds every scenario inside its own reserved database', function (int $number) {
    $builder = new PiiScenarioBuilder(null, 0.001);
    $builder->recreateDatabase($number);
    piiScenarios()[$number]['build']($builder);

    $databases = [];
    foreach ($builder->statements as $sql) {
        if (preg_match('/^\s*(?:DROP|CREATE)\s+DATABASE\s+(?:IF\s+EXISTS\s+)?`([^`]+)`|^\s*USE\s+`([^`]+)`/i', $sql, $matches) === 1) {
            $databases[] = $matches[1] !== '' ? $matches[1] : $matches[2];
        }
    }

    $tables = count(array_filter($builder->statements, fn (string $sql): bool => str_starts_with(ltrim($sql), 'CREATE TABLE')));

    expect(array_unique($databases))->toBe(["pii_test_scenario_{$number}"])
        ->and($tables)->toBeGreaterThanOrEqual(5)
        ->and($builder->insertedRows)->toBeGreaterThan(0);
})->with([1, 2, 3, 4, 5, 6, 7]);

it('documents the expected outcome of every table', function (int $number) {
    $builder = new PiiScenarioBuilder(null, 0.001);
    piiScenarios()[$number]['build']($builder);

    foreach ($builder->statements as $sql) {
        if (str_starts_with(ltrim($sql), 'CREATE TABLE')) {
            expect($sql)->toContain('ATTENDU :');
        }
    }
})->with([1, 2, 3, 4, 5, 6, 7]);
