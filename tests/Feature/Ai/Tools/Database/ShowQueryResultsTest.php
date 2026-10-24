<?php

use App\Ai\Tools\Database\ShowQueryResults;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('show-query-results-').'.sqlite';
    $this->driver = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...))->driver_config->getDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('describes the result of the query without giving its rows to the agent', function () {
    $result = (string) new ShowQueryResults($this->driver)->handle(new Request([
        'query' => 'SELECT name, city FROM customers WHERE city IS NOT NULL;',
        'bindings' => [],
        'title' => 'Clients avec une ville',
    ]));

    expect(json_decode($result, true))->toBe([
        'success' => true,
        'title' => 'Clients avec une ville',
        'columns' => ['name', 'city'],
        'totalRows' => 2,
    ]);
});

it('refuses a query that would change the database', function () {
    $result = (string) new ShowQueryResults($this->driver)->handle(new Request([
        'query' => 'DELETE FROM orders',
        'bindings' => [],
    ]));

    expect(json_decode($result, true))->toMatchArray(['success' => false]);
    expect($this->driver->getConnection()->table('orders')->count())->toBe(3);
});

it('gives the error of the database to the agent so it can fix its query', function () {
    $result = (string) new ShowQueryResults($this->driver)->handle(new Request([
        'query' => 'SELECT unknown_column FROM customers',
        'bindings' => [],
    ]));

    expect(json_decode($result, true))->toMatchArray(['success' => false])
        ->and(json_decode($result, true)['error'])->toContain('unknown_column');
});
