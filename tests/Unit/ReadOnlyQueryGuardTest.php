<?php

use App\Database\ReadOnlyQueryGuard;
use Tests\TestCase;

pest()->extend(TestCase::class);

it('accepts a single SELECT and returns it without its final semicolon', function (string $sql, string $expected) {
    expect(ReadOnlyQueryGuard::assertSelect($sql))->toBe($expected);
})->with([
    'select' => ['SELECT * FROM users;', 'SELECT * FROM users'],
    'common table expression' => ["  WITH recent AS (SELECT id FROM orders) SELECT * FROM recent ;\n", 'WITH recent AS (SELECT id FROM orders) SELECT * FROM recent'],
]);

it('rejects a statement that is not a SELECT where a SELECT is expected', function (string $sql) {
    ReadOnlyQueryGuard::assertSelect($sql);
})->with([
    'explain' => ['EXPLAIN SELECT * FROM users'],
    'show' => ['SHOW TABLES'],
    'pragma' => ['PRAGMA table_info(users)'],
])->throws(RuntimeException::class);

it('rejects a query that could change the database', function (string $sql) {
    ReadOnlyQueryGuard::assertReadOnly($sql);
})->with([
    'delete' => ['DELETE FROM users'],
    'write hidden in a select' => ['SELECT * FROM users WHERE id IN (DELETE FROM users RETURNING id)'],
    'stacked statements' => ['SELECT 1; SELECT 2;'],
    'configuration pragma' => ['PRAGMA query_only = OFF'],
    'pragma outside the allow list' => ['PRAGMA writable_schema'],
])->throws(RuntimeException::class);

it('rejects stacked statements in a SELECT meant to be wrapped in a subquery', function () {
    ReadOnlyQueryGuard::assertSelect('SELECT 1; SELECT 2');
})->throws(RuntimeException::class, 'les requêtes multiples');

it('rejects stacked statements in a read-only query', function (string $sql) {
    ReadOnlyQueryGuard::assertReadOnly($sql);
})->with([
    'one semicolon' => ['SELECT 1; SET @x = 1'],
    'after a text value' => ["SELECT 'a'; SET @x = 1"],
    'after an allowed pragma' => ['PRAGMA table_info(users); SET @x = 1'],
    'behind a backslash that does not escape' => ["SELECT 'a\\'; SET @x = 1; --'"],
    'behind a dollar-quoted quote' => ["SELECT \$\$ ' \$\$; SET @x = 1; --'"],
    'after a hash operator' => ['SELECT 1 # 2; SET @x = 1'],
])->throws(RuntimeException::class, 'les requêtes multiples');

it('accepts a single read-only statement whose semicolons are only text values or final', function (string $sql) {
    ReadOnlyQueryGuard::assertReadOnly($sql);

    expect(ReadOnlyQueryGuard::assertSelect($sql))->toBe(rtrim($sql, ';'));
})->with([
    'final semicolon' => ['SELECT * FROM users;'],
    'text value' => ["SELECT * FROM users WHERE name = 'a;b'"],
    'text value with an escaped quote' => ["SELECT * FROM users WHERE name = 'l''a;mi';"],
]);

it('returns the SELECT whose plan is asked for without the EXPLAIN keyword it starts with', function (string $sql, string $expected) {
    expect(ReadOnlyQueryGuard::assertExplainable($sql))->toBe($expected);
})->with([
    'select' => ['SELECT * FROM users', 'SELECT * FROM users'],
    'explain' => ['EXPLAIN SELECT * FROM users', 'SELECT * FROM users'],
    'explain in json' => ['explain format=json SELECT * FROM users', 'SELECT * FROM users'],
    'explain query plan' => ['EXPLAIN QUERY PLAN SELECT * FROM users', 'SELECT * FROM users'],
    'explain with options' => ['EXPLAIN (FORMAT JSON, COSTS) SELECT * FROM users', 'SELECT * FROM users'],
    'leading comments' => ["-- slow query\n/* report */ SELECT * FROM users", 'SELECT * FROM users'],
]);

it('rejects a query whose plan cannot be computed without running it, changing data or locking rows', function (string $sql) {
    ReadOnlyQueryGuard::assertExplainable($sql);
})->with([
    'explain analyze' => ['EXPLAIN ANALYZE SELECT * FROM users'],
    'explain with the analyze option' => ['EXPLAIN (ANALYZE, BUFFERS) SELECT * FROM users'],
    'select into' => ['SELECT * INTO backup FROM users'],
    'select for update' => ['SELECT * FROM users FOR UPDATE'],
    'lock in share mode' => ['SELECT * FROM users LOCK IN SHARE MODE'],
    'update' => ['UPDATE users SET name = 1'],
])->throws(RuntimeException::class);
