<?php

use App\Database\Drivers\MySQLDriver;

$engines = ['carts' => 'InnoDB', 'cart_items' => 'InnoDB', 'logs' => 'MyISAM'];

it('dry runs a write whose tables are all InnoDB', function (string $sql) use ($engines) {
    expect(MySQLDriver::writeTouchesOnlyInnoDbTables($sql, $engines, ['other_db']))->toBeTrue();
})->with([
    'single table' => ['DELETE FROM carts WHERE id = 1'],
    'aliased join' => ['DELETE ci FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.updated_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)'],
    'quoted update' => ['UPDATE `Carts` SET status = ? WHERE id = ?'],
]);

it('refuses to dry run a write that may touch a table the rollback cannot restore', function (string $sql) use ($engines) {
    expect(MySQLDriver::writeTouchesOnlyInnoDbTables($sql, $engines, ['other_db']))->toBeFalse();
})->with([
    'myisam table' => ['DELETE FROM logs'],
    'join with a myisam table' => ['DELETE c FROM carts c JOIN logs l ON l.cart_id = c.id'],
    'another database' => ['DELETE FROM other_db.carts'],
    'unknown table' => ['DELETE FROM missing'],
]);
