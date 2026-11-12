<?php

use App\Database\Exceptions\PrivilegeException;
use App\Database\QueryPrivilegeGuard;
use App\Enums\PrivilegeEnum;
use App\Services\Privileges\Data\TablePrivileges;
use Tests\TestCase;

pest()->extend(TestCase::class);

/**
 * @param  list<PrivilegeEnum>  $onProject
 * @param  array<string, list<PrivilegeEnum>>  $onTables
 */
function assertQueryPrivileges(string $sql, array $onProject = [], array $onTables = []): void
{
    QueryPrivilegeGuard::assertAllowed($sql, new TablePrivileges($onProject, $onTables), fn (): array => ['customers', 'orders', 'products'], 'alice', ['shop']);
}

it('runs any statement for whoever holds its privileges on the whole project', function (string $sql) {
    assertQueryPrivileges($sql, [PrivilegeEnum::Select, PrivilegeEnum::Delete]);
})->with([
    'read' => ['SELECT * FROM customers'],
    'catalog' => ['SELECT * FROM information_schema.tables'],
    'other database' => ['SELECT * FROM otherdb.secret'],
    'nested delete' => ['WITH gone AS (DELETE FROM orders RETURNING *) SELECT * FROM gone'],
])->throwsNoExceptions();

it('runs any statement for the root', function () {
    QueryPrivilegeGuard::assertAllowed('CALL anything()', TablePrivileges::unrestricted(), fn (): array => [], 'opendb');
})->throwsNoExceptions();

it('lets a user run what their privileges on single tables allow', function (string $sql, array $onTables, array $onProject = []) {
    assertQueryPrivileges($sql, $onProject, $onTables);
})->with([
    'read' => ['SELECT * FROM orders', ['orders' => [PrivilegeEnum::Select]]],
    'quoted and qualified' => ['select * from "public"."Orders"', ['orders' => [PrivilegeEnum::Select]]],
    'qualified by its database' => ['SELECT * FROM `shop`.`orders`', ['orders' => [PrivilegeEnum::Select]]],
    'explained' => ['EXPLAIN QUERY PLAN SELECT * FROM orders', ['orders' => [PrivilegeEnum::Select]]],
    'write keyword in a text value' => ["SELECT * FROM orders WHERE status = 'deleted' OR status = 'delete'", ['orders' => [PrivilegeEnum::Select]]],
    'replace function' => ["SELECT REPLACE(status, 'a', 'b') FROM orders", ['orders' => [PrivilegeEnum::Select]]],
    'inspection' => ['PRAGMA table_info(orders)', ['orders' => [PrivilegeEnum::Select]]],
    'update without reading' => ["UPDATE orders SET status = 'paid'", ['orders' => [PrivilegeEnum::Update]]],
    'update reading its rows' => ["UPDATE orders SET status = 'paid' WHERE id = 1", ['orders' => [PrivilegeEnum::Update, PrivilegeEnum::Select]]],
    'insert reading another table' => ['INSERT INTO orders (total) SELECT price FROM products', ['orders' => [PrivilegeEnum::Insert], 'products' => [PrivilegeEnum::Select]]],
    'delete' => ['DELETE FROM orders WHERE id = 1', ['orders' => [PrivilegeEnum::Delete, PrivilegeEnum::Select]]],
    'index' => ['CREATE INDEX orders_total ON orders (total)', ['orders' => [PrivilegeEnum::Index]]],
    'alter' => ['ALTER TABLE orders ADD COLUMN note TEXT', ['orders' => [PrivilegeEnum::Alter]]],
    'drop' => ['DROP TABLE IF EXISTS orders', ['orders' => [PrivilegeEnum::Drop]]],
    'truncate' => ['TRUNCATE TABLE orders', ['orders' => [PrivilegeEnum::Drop]]],
    'create from a readable table' => ['CREATE TABLE summary AS SELECT * FROM orders', ['orders' => [PrivilegeEnum::Select]], [PrivilegeEnum::Create]],
])->throwsNoExceptions();

it('refuses what the privileges on single tables do not allow', function (string $sql, array $onTables, string $message, array $onProject = []) {
    expect(fn () => assertQueryPrivileges($sql, $onProject, $onTables))->toThrow(PrivilegeException::class, $message);
})->with([
    'another table' => ['SELECT * FROM customers', ['orders' => [PrivilegeEnum::Select]], 'Commande SELECT refusée à l’utilisateur « alice » sur la table « customers »'],
    'joined table' => ['SELECT * FROM orders o JOIN customers c ON c.id = o.customer_id', ['orders' => [PrivilegeEnum::Select]], 'sur la table « customers »'],
    'table in a text value' => ["SELECT * FROM orders WHERE note = 'customers'", ['orders' => [PrivilegeEnum::Select]], 'sur la table « customers »'],
    'write with a read privilege' => ['UPDATE orders SET total = 1', ['orders' => [PrivilegeEnum::Select]], 'Commande UPDATE refusée'],
    'update reading its rows' => ['UPDATE orders SET total = 1 WHERE id = 2', ['orders' => [PrivilegeEnum::Update]], 'Commande SELECT refusée'],
    'unknown table' => ['INSERT INTO archive VALUES (1)', ['orders' => [PrivilegeEnum::Insert]], 'Commande INSERT refusée à l’utilisateur « alice » sur la table « archive »'],
    'several tables changed' => ['UPDATE orders JOIN customers ON customers.id = orders.customer_id SET orders.total = 1', ['orders' => [PrivilegeEnum::Update, PrivilegeEnum::Select], 'customers' => [PrivilegeEnum::Select]], 'il faut le privilège UPDATE sur chacune'],
    'several tables dropped' => ['DROP TABLE orders, customers', ['orders' => [PrivilegeEnum::Drop]], 'Commande DROP refusée à l’utilisateur « alice » sur la table « customers »'],
    'system catalog' => ['SELECT * FROM information_schema.tables', ['orders' => [PrivilegeEnum::Select]], 'catalogues système'],
    'listing the tables' => ['SHOW TABLES', ['orders' => [PrivilegeEnum::Select]], 'catalogues système'],
    'other database' => ['SELECT * FROM otherdb.secret', ['orders' => [PrivilegeEnum::Select]], 'n’appartient pas à la base du projet'],
    'other database in a list' => ['SELECT * FROM orders, otherdb.secret', ['orders' => [PrivilegeEnum::Select]], 'n’appartient pas à la base du projet'],
    'nested delete' => ['WITH gone AS (DELETE FROM orders RETURNING *) SELECT * FROM gone', ['orders' => [PrivilegeEnum::Select, PrivilegeEnum::Delete]], 'écriture imbriquée'],
    'select into' => ['SELECT * INTO copy FROM orders', ['orders' => [PrivilegeEnum::Select]], 'écriture imbriquée'],
    'dynamic sql' => ["SELECT * FROM orders WHERE id = (SELECT load_file('/etc/passwd'))", ['orders' => [PrivilegeEnum::Select]], 'SQL dynamique'],
    'unknown statement' => ['CALL purge()', ['orders' => [PrivilegeEnum::Select]], 'privilèges sur tout le projet'],
    'index without its table' => ['DROP INDEX orders_total', ['orders' => [PrivilegeEnum::Index]], 'Commande INDEX refusée à l’utilisateur « alice » sur ce projet'],
    'create without the privilege' => ['CREATE TABLE archive (id INT)', ['orders' => [PrivilegeEnum::Select]], 'Commande CREATE refusée'],
]);

it('refuses several statements at once to a user holding privileges on single tables', function () {
    assertQueryPrivileges('SELECT * FROM orders; DROP TABLE orders', onTables: ['orders' => [PrivilegeEnum::Select]]);
})->throws(RuntimeException::class, 'les requêtes multiples');
