<?php

use App\Database\AdminStatementGuard;
use Tests\TestCase;

pest()->extend(TestCase::class);

it('rejects an operation reserved to a database administrator', function (string $sql) {
    AdminStatementGuard::assertNotAdministrative($sql);
})->with([
    'create user' => ["CREATE USER 'bob'@'%' IDENTIFIED BY 'secret'"],
    'create user if not exists' => ["CREATE USER IF NOT EXISTS bob IDENTIFIED BY 'secret'"],
    'create or replace user' => ["CREATE OR REPLACE USER bob IDENTIFIED BY 'secret'"],
    'alter user' => ["ALTER USER bob IDENTIFIED BY 'secret'"],
    'drop user' => ['DROP USER bob'],
    'rename user' => ['RENAME USER bob TO alice'],
    'create role' => ['CREATE ROLE reader'],
    'drop role' => ['DROP ROLE reader'],
    'alter group' => ['ALTER GROUP staff ADD USER bob'],
    'grant' => ['GRANT ALL ON *.* TO bob'],
    'revoke' => ['REVOKE SELECT ON users FROM bob'],
    'set password' => ["SET PASSWORD FOR bob = 'secret'"],
    'set own password' => ["SET PASSWORD = 'secret'"],
    'set role' => ['SET ROLE admin'],
    'reassign owned' => ['REASSIGN OWNED BY bob TO alice'],
    'create database' => ['CREATE DATABASE shop'],
    'drop database' => ['DROP DATABASE shop'],
    'drop schema' => ['DROP SCHEMA shop'],
    'create extension' => ['CREATE EXTENSION pgcrypto'],
    'create tablespace' => ["CREATE TABLESPACE fast LOCATION '/mnt'"],
    'alter system' => ['ALTER SYSTEM SET max_connections = 10'],
    'shutdown' => ['SHUTDOWN'],
    'restart' => ['RESTART'],
    'kill' => ['KILL 42'],
    'flush' => ['FLUSH PRIVILEGES'],
    'purge binary logs' => ['PURGE BINARY LOGS BEFORE NOW()'],
    'install plugin' => ["INSTALL PLUGIN audit SONAME 'audit.so'"],
    'change replication source' => ["CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'"],
    'stop replica' => ['STOP REPLICA'],
    'set global' => ['SET GLOBAL max_connections = 10'],
    'set global through a variable' => ['SET @@GLOBAL.max_connections = 10'],
    'terminate a backend' => ['SELECT pg_terminate_backend(42)'],
    'attach a database' => ["ATTACH DATABASE '/etc/passwd' AS leak"],
    'writable schema' => ['PRAGMA writable_schema = ON'],
    'write to a server file' => ["SELECT * FROM users INTO OUTFILE '/tmp/users'"],
    'read a server file' => ["INSERT INTO notes (body) VALUES (LOAD_FILE('/etc/passwd'))"],
    'load data' => ["LOAD DATA INFILE '/tmp/users' INTO TABLE users"],
    'copy to a program' => ["COPY users TO PROGRAM 'sh'"],
    'prepared statement' => ["PREPARE admin FROM 'CREATE USER bob'"],
    'execute a prepared statement' => ['EXECUTE admin'],
    'execute immediate' => ["EXECUTE IMMEDIATE 'CRE' || 'ATE USER bob'"],
    'anonymous block' => ["DO \$\$ BEGIN EXECUTE 'x'; END \$\$"],
    'lowercase' => ['create user bob'],
    'split over lines' => ["CREATE\n\tUSER bob"],
    'leading comment' => ['/* report */ CREATE USER bob'],
    'comment between keywords' => ['CREATE/**/USER bob'],
    'line comment between keywords' => ["CREATE -- note\nUSER bob"],
    'executable comment' => ['/*!50000 CREATE USER bob */'],
    'mariadb executable comment' => ['/*M!100100 CREATE USER bob */'],
    'executable comment splitting keywords' => ['CREATE /*!50000 USER */ bob'],
    'nested comment' => ['CREATE /* a /* b */ */ USER bob'],
    'stacked after a statement' => ['UPDATE users SET age = 1; CREATE USER bob'],
    'hidden after an opening comment in a string' => ["INSERT INTO notes VALUES ('/*'); CREATE/**/USER bob IDENTIFIED BY 'x*/'"],
    'hidden after a backslash that does not escape' => ["INSERT INTO notes VALUES ('a\\'); CREATE/**/USER bob --')"],
    'inside a stored procedure' => ["CREATE PROCEDURE admin() CREATE USER bob IDENTIFIED BY 'secret'"],
    'inside an event' => ['CREATE EVENT admin ON SCHEDULE EVERY 1 DAY DO GRANT ALL ON *.* TO bob'],
])->throws(RuntimeException::class, 'réservée à un administrateur');

it('accepts the changes of data and structure of an application', function (string $sql) {
    expect(AdminStatementGuard::isAdministrative($sql))->toBeFalse();
})->with([
    'insert' => ['INSERT INTO users (name) VALUES (:name)'],
    'update of a password column' => ['UPDATE users SET password = :password WHERE id = :id'],
    'update of a role column' => ['UPDATE users SET role = :role WHERE id = :id'],
    'update of a global column' => ['UPDATE settings SET global = 1'],
    'update of a kill column' => ['UPDATE players SET kill = 1, flush = 0'],
    'delete' => ['DELETE FROM users WHERE id = :id'],
    'truncate' => ['TRUNCATE TABLE users'],
    'create a table named user' => ['CREATE TABLE user (id INT)'],
    'create a table with a role column' => ['CREATE TABLE members (id INT, role VARCHAR(20))'],
    'drop a table' => ['DROP TABLE users'],
    'add a role column' => ['ALTER TABLE users ADD COLUMN role VARCHAR(20)'],
    'drop a role column' => ['ALTER TABLE users DROP COLUMN role'],
    'rename a user column' => ['ALTER TABLE users RENAME COLUMN user TO member'],
    'create an index' => ['CREATE INDEX users_role_index ON users (role)'],
    'create a view' => ['CREATE VIEW active_users AS SELECT * FROM users'],
    'column named copy' => ['UPDATE files SET copy = 1'],
]);

it('tells the model how to change a column named like an administration keyword', function () {
    AdminStatementGuard::assertNotAdministrative('ALTER TABLE users DROP role');
})->throws(RuntimeException::class, 'COLUMN');
