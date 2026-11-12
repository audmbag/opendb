<?php

use App\Ai\Tools\Database\CreateRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('create-record-').'.sqlite';
});

afterEach(function () {
    @unlink($this->databasePath);
});

function contactsTable(Builder $schema): void
{
    $schema->create('contacts', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->boolean('active')->default(true);
    });
}

it('inserts the values submitted in the form once the call is approved', function () {
    $project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, contactsTable(...));
    $driver = $project->driver_config->getDriver();
    Cache::put(CreateRecord::submissionCacheKey('call_1'), ['table' => 'contacts', 'values' => ['email' => 'ada@example.com', 'active' => false]]);

    $result = (string) new CreateRecord($driver)->handle(new Request(['table' => 'contacts'], 'call_1'));

    expect($result)->toBe('Record created in `contacts`: {"id":1,"email":"ada@example.com","active":false}');
    expect($driver->getConnection()->table('contacts')->get(['id', 'email', 'active'])->map(fn ($row) => (array) $row)->all())
        ->toBe([['id' => 1, 'email' => 'ada@example.com', 'active' => 0]]);
    expect(Cache::has(CreateRecord::submissionCacheKey('call_1')))->toBeFalse();
});

it('does not insert anything when no values were submitted for the call', function () {
    $project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, contactsTable(...));
    $driver = $project->driver_config->getDriver();

    $result = (string) new CreateRecord($driver)->handle(new Request(['table' => 'contacts'], 'call_1'));

    expect($result)->toBe('No form data was submitted for this call: the record was not created.');
    expect($driver->getConnection()->table('contacts')->count())->toBe(0);
});

it('does not insert values submitted for another table than the one of the call', function () {
    $project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, contactsTable(...));
    $driver = $project->driver_config->getDriver();
    Cache::put(CreateRecord::submissionCacheKey('call_1'), ['table' => 'users', 'values' => ['email' => 'ada@example.com']]);

    $result = (string) new CreateRecord($driver)->handle(new Request(['table' => 'contacts'], 'call_1'));

    expect($result)->toBe('No form data was submitted for this call: the record was not created.');
    expect($driver->getConnection()->table('contacts')->count())->toBe(0);
});
