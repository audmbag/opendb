<?php

use App\Database\Drivers\BaseDriver;
use App\Database\Exceptions\DatabaseCheckException;
use App\Models\User;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/opendb_'.uniqid();
    mkdir($this->directory);
    $this->path = $this->directory.'/clone.sqlite';
});

afterEach(function () {
    array_map('unlink', glob($this->directory.'/*'));
    rmdir($this->directory);
});

function sqliteConfig(string $path, mixed $create = null): array
{
    return array_filter([
        'driver_id' => 'sqlite',
        'database' => $path,
        'create_database_if_not_exists' => $create,
    ], fn (mixed $value) => $value !== null);
}

it('fails to test a missing sqlite database without the create option', function () {
    $driver = BaseDriver::for(sqliteConfig($this->path));

    expect(fn () => $driver->testConnection())->toThrow(Exception::class)
        ->and($this->path)->not->toBeFile();
});

it('accepts a missing sqlite database with the create option without creating it', function () {
    $driver = BaseDriver::for(sqliteConfig($this->path, true));

    expect($driver->testConnection())->toBeTrue()
        ->and($driver->willCreateDatabase())->toBeTrue()
        ->and($this->path)->not->toBeFile();
});

it('creates a missing sqlite database', function () {
    $driver = BaseDriver::for(sqliteConfig($this->path, true));

    expect($driver->createDatabaseIfNotExists())->toBeTrue()
        ->and($this->path)->toBeFile();
});

it('does not recreate an existing sqlite database', function () {
    touch($this->path);

    $driver = BaseDriver::for(sqliteConfig($this->path, true));

    expect($driver->testConnection())->toBeTrue()
        ->and($driver->willCreateDatabase())->toBeFalse()
        ->and($driver->createDatabaseIfNotExists())->toBeFalse();
});

it('rejects a sqlite database that cannot be created', function () {
    $driver = BaseDriver::for(sqliteConfig($this->directory.'/missing/clone.sqlite', true));

    expect(fn () => $driver->testConnection())->toThrow(DatabaseCheckException::class);
});

it('validates the create option on the test connection endpoint', function (mixed $create, int $status, bool $success) {
    $response = $this->actingAs(User::factory()->create())
        ->postJson('/helpers/test-connection', sqliteConfig($this->path, $create));

    $response->assertStatus($status);

    if ($status === 200) {
        $response->assertJson(['success' => $success]);
    }
})->with([
    'checked switch' => ['on', 200, true],
    'boolean true' => [true, 200, true],
    'unchecked switch' => [null, 422, false],
    'false' => [false, 422, false],
]);
