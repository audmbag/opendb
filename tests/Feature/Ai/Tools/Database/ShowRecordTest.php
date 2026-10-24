<?php

use App\Ai\Tools\Database\ShowRecord;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('show-record-').'.sqlite';
    $this->driver = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...))->driver_config->getDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('confirms the record displayed', function () {
    $result = (string) new ShowRecord($this->driver)->handle(new Request(['tableName' => 'customers', 'key' => ['name' => 'Grace']]));

    expect(json_decode($result, true))->toBe(['success' => true, 'table' => 'customers', 'key' => ['name' => 'Grace']]);
});

it('tells the agent when no record matches the key', function () {
    $result = (string) new ShowRecord($this->driver)->handle(new Request(['tableName' => 'customers', 'key' => ['id' => 99]]));

    expect(json_decode($result, true))->toBe([
        'success' => false,
        'error' => 'Aucun enregistrement de la table [customers] ne correspond à la clé {"id":99}.',
    ]);
});

it('refuses a key on a column the table does not have', function () {
    $result = (string) new ShowRecord($this->driver)->handle(new Request(['tableName' => 'customers', 'key' => ['email' => 'ada@example.com']]));

    expect(json_decode($result, true))->toBe([
        'success' => false,
        'error' => 'La clé doit être un objet `colonne => valeur` portant sur des colonnes de la table [customers] : id, name, city.',
    ]);
});
