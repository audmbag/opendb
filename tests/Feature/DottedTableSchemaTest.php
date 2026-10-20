<?php

use App\Database\Drivers\BaseDriver;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-dotted-').'.sqlite';
    File::put($this->path, '');

    $pdo = new PDO('sqlite:'.$this->path);
    $pdo->exec('CREATE TABLE "1.5e3" (id INTEGER PRIMARY KEY, email VARCHAR(191) NOT NULL, name VARCHAR(200) NULL)');
    $pdo->exec('CREATE UNIQUE INDEX "1.5e3_email_unique" ON "1.5e3" (email)');
    $pdo->exec('CREATE TABLE "v2.orders" (id INTEGER PRIMARY KEY, customer_id INTEGER REFERENCES "1.5e3" (id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(191))');

    $this->schema = collect(BaseDriver::for(['driver_id' => 'sqlite', 'database' => $this->path])->getSchema())->keyBy('name');
});

afterEach(function () {
    File::delete($this->path);
});

it('reads the structure of tables whose name contains a dot', function () {
    expect(array_column($this->schema['1.5e3']['columns'], 'name'))->toBe(['id', 'email', 'name'])
        ->and($this->schema['1.5e3']['columns'][1])->toMatchArray(['type' => 'varchar(191)', 'nullable' => false])
        ->and($this->schema['1.5e3']['primary_key'])->toBe(['id'])
        ->and($this->schema['1.5e3']['indexes'])->toBe([['name' => '1.5e3_email_unique', 'columns' => ['email'], 'unique' => true]])
        ->and($this->schema['v2.orders']['foreign_keys'])->toHaveCount(1)
        ->and($this->schema['v2.orders']['foreign_keys'][0])->toMatchArray([
            'columns' => ['customer_id'],
            'references_table' => '1.5e3',
            'references_columns' => ['id'],
            'on_delete' => 'cascade',
        ]);
});

it('leaves the other tables as truss reads them', function () {
    expect(array_column($this->schema['users']['columns'], 'name'))->toBe(['id', 'email'])
        ->and($this->schema['users']['primary_key'])->toBe(['id']);
});
