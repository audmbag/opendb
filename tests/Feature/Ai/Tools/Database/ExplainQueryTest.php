<?php

use App\Ai\Tools\Database\ExplainQuery;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('explain-query-').'.sqlite';
    $this->driver = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...))->driver_config->getDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('summarizes the plan for the agent and flags a full scan of a table', function () {
    $result = (string) new ExplainQuery($this->driver)->handle(new Request([
        'query' => 'SELECT * FROM orders WHERE status = :status',
        'bindings' => ['status' => 'paid'],
    ]));

    expect(json_decode($result, true))->toBe([
        'success' => true,
        'steps' => ['SCAN orders ⚠ Parcours complet de la table'],
    ]);
});

it('does not flag a search through an index', function () {
    $result = (string) new ExplainQuery($this->driver)->handle(new Request([
        'query' => 'SELECT * FROM orders WHERE id = :id',
        'bindings' => ['id' => 1],
    ]));

    expect(json_decode($result, true)['steps'])->each->not->toContain('⚠');
});

it('refuses a query that is not a SELECT', function () {
    $result = (string) new ExplainQuery($this->driver)->handle(new Request([
        'query' => 'EXPLAIN ANALYZE DELETE FROM orders',
        'bindings' => [],
    ]));

    expect(json_decode($result, true))->toMatchArray(['success' => false]);
    expect($this->driver->getConnection()->table('orders')->count())->toBe(3);
});
