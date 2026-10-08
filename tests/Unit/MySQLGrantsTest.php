<?php

use App\Database\Drivers\MySQLDriver;

it('allows creating a database with global privileges', function (string $grant) {
    expect(MySQLDriver::grantsAllowCreatingDatabase([$grant], 'clone_db'))->toBeTrue();
})->with([
    'all privileges' => ['GRANT ALL PRIVILEGES ON *.* TO `root`@`localhost` WITH GRANT OPTION'],
    'create' => ["GRANT SELECT, CREATE, DROP ON *.* TO 'dev'@'%'"],
    'mariadb' => ['GRANT ALL PRIVILEGES ON *.* TO `root`@`localhost` IDENTIFIED VIA mysql_native_password USING \'xxx\''],
]);

it('allows creating a database with privileges on a matching schema', function (string $grant) {
    expect(MySQLDriver::grantsAllowCreatingDatabase([$grant], 'clone_db'))->toBeTrue();
})->with([
    'exact schema' => ['GRANT ALL PRIVILEGES ON `clone_db`.* TO `dev`@`%`'],
    'wildcard' => ['GRANT CREATE ON `clone%`.* TO `dev`@`%`'],
    'escaped underscore' => ['GRANT CREATE ON `clone\_db`.* TO `dev`@`%`'],
]);

it('refuses creating a database without a sufficient privilege', function (array $grants) {
    expect(MySQLDriver::grantsAllowCreatingDatabase($grants, 'clone_db'))->toBeFalse();
})->with([
    'usage only' => [["GRANT USAGE ON *.* TO 'dev'@'%'"]],
    'create-like privileges only' => [["GRANT CREATE VIEW, CREATE ROUTINE, CREATE USER, CREATE TEMPORARY TABLES ON *.* TO 'dev'@'%'"]],
    'other schema' => [['GRANT ALL PRIVILEGES ON `other_db`.* TO `dev`@`%`']],
]);

it('refuses creating a database with privileges on a single table', function () {
    expect(MySQLDriver::grantsAllowCreatingDatabase(['GRANT ALL PRIVILEGES ON `clone_db`.`users` TO `dev`@`%`'], 'clone_db'))->toBeFalse();
});

it('does not treat an escaped underscore as a wildcard', function () {
    expect(MySQLDriver::grantsAllowCreatingDatabase(['GRANT CREATE ON `clone\_db`.* TO `dev`@`%`'], 'cloneXdb'))->toBeFalse();
});
