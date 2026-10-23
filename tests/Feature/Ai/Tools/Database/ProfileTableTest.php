<?php

use App\Ai\Tools\Database\ProfileTable;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('profile-table-').'.sqlite';
    $this->driver = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...))->driver_config->getDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('confirms the table whose profile is displayed', function () {
    $result = (string) new ProfileTable($this->driver)->handle(new Request(['tableName' => 'customers']));

    expect(json_decode($result, true))->toBe(['success' => true, 'table' => 'customers']);
});

it('tells the agent the table does not exist', function () {
    $result = (string) new ProfileTable($this->driver)->handle(new Request(['tableName' => 'invoices']));

    expect(json_decode($result, true))->toMatchArray(['success' => false])
        ->and(json_decode($result, true)['error'])->toContain('[invoices]');
});
