<?php

use App\Database\Exceptions\HiddenTableException;
use App\Database\HiddenTableGuard;
use Tests\TestCase;

pest()->extend(TestCase::class);

it('lets every query run while no table is hidden', function (string $sql) {
    expect(HiddenTableGuard::rejects($sql, []))->toBeFalse();
})->with([
    'table' => ['SELECT * FROM customers'],
    'system catalog' => ['SELECT * FROM information_schema.columns'],
    'dynamic sql' => ["PREPARE stmt FROM 'SELECT 1'"],
]);

it('rejects a query citing a hidden table however it is written', function (string $sql) {
    HiddenTableGuard::assertQueryAllowed($sql, ['customers']);
})->with([
    'unquoted' => ['SELECT * FROM customers'],
    'other case' => ['select * from Customers'],
    'double quotes' => ['SELECT * FROM "customers"'],
    'backticks' => ['SELECT * FROM `customers`'],
    'brackets' => ['SELECT * FROM [customers]'],
    'schema qualified' => ['SELECT * FROM public.customers'],
    'joined' => ['SELECT o.id FROM orders o JOIN customers c ON c.id = o.customer_id'],
    'subquery' => ['SELECT * FROM orders WHERE customer_id IN (SELECT id FROM customers)'],
    'write' => ['DELETE FROM customers WHERE id = 1'],
    'text value' => ["SELECT * FROM orders WHERE note = 'customers'"],
])->throws(HiddenTableException::class, 'elle cite la table [customers]');

it('rejects a query citing a hidden table whose name holds a dot', function () {
    HiddenTableGuard::assertQueryAllowed('SELECT * FROM "v2.orders"', ['v2.orders']);
})->throws(HiddenTableException::class, 'elle cite la table [v2.orders]');

it('lets a query citing only tables whose name contains a hidden one run', function (string $sql) {
    expect(HiddenTableGuard::rejects($sql, ['customers']))->toBeFalse();
})->with([
    'prefix' => ['SELECT * FROM customers_archive'],
    'suffix' => ['SELECT * FROM old_customers'],
    'column' => ['SELECT customers_count FROM stats'],
]);

it('rejects the system catalogs while a table is hidden', function (string $sql) {
    HiddenTableGuard::assertQueryAllowed($sql, ['customers']);
})->with([
    'information schema' => ["SELECT column_name FROM information_schema.columns WHERE table_name LIKE 'cust%'"],
    'postgres catalog' => ['SELECT attname FROM pg_attribute'],
    'postgres statistics' => ['SELECT * FROM pg_stat_user_tables'],
    'sqlite schema' => ['SELECT sql FROM sqlite_master'],
    'sqlite pragma function' => ["SELECT * FROM pragma_table_info('cust' || 'omers')"],
    'mysql schema' => ['SELECT * FROM mysql.innodb_table_stats'],
])->throws(HiddenTableException::class, 'catalogues système');

it('rejects dynamic sql while a table is hidden', function (string $sql) {
    HiddenTableGuard::assertQueryAllowed($sql, ['customers']);
})->with([
    'prepare' => ["PREPARE stmt FROM CONCAT('SELECT * FROM cust', 'omers')"],
    'execute' => ['EXECUTE stmt'],
    'postgres anonymous block' => ['DO $$ BEGIN PERFORM 1; END $$'],
    'query to xml' => ["SELECT query_to_xml('SELECT * FROM cust' || 'omers', true, false, '')"],
    'regclass cast' => ["SELECT pg_relation_size(('cust' || 'omers')::regclass)"],
    'unicode identifier' => ['SELECT * FROM U&"\0063ustomers"'],
])->throws(HiddenTableException::class, 'SQL dynamique');

it('lets a trigger executing a function be created while a table is hidden', function () {
    expect(HiddenTableGuard::rejects('CREATE TRIGGER audit AFTER INSERT ON orders FOR EACH ROW EXECUTE FUNCTION log_order()', ['customers']))->toBeFalse()
        ->and(HiddenTableGuard::rejects('INSERT INTO orders (id) VALUES (1) ON CONFLICT DO NOTHING', ['customers']))->toBeFalse();
});

it('rejects only the tables hidden with that exact name', function () {
    HiddenTableGuard::assertTableAllowed('orders', ['customers']);

    expect(fn () => HiddenTableGuard::assertTableAllowed('customers', ['customers']))
        ->toThrow(HiddenTableException::class, 'La table [customers] est désactivée pour l\'agent');
});
