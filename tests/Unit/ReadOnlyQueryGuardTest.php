<?php

use App\Database\ReadOnlyQueryGuard;

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
